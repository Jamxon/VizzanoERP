<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentCalculationService
{
    private const DEFAULT_DOLLAR_RATE = 12000;

    /**
     * SewingOutput yaratilganda to'lovlarni hisoblash
     * 
     * @param int $orderSubmodelId
     * @param int $quantity - Tikish natijasida kiritilgan soni
     * @return array
     */
    public function calculatePaymentsForSewingOutput(int $orderSubmodelId, int $quantity): array
    {
        try {
            DB::beginTransaction();

            // Order ma'lumotlarini olish
            $data = $this->getOrderData($orderSubmodelId);
            
            if (!$data) {
                throw new Exception("Order ma'lumotlari topilmadi");
            }

            $payments = [];

            // 1. Kesuvchilar uchun hisoblash
            $cuttingPayments = $this->calculateCuttingPayments($data, $quantity);
            $payments = array_merge($payments, $cuttingPayments);

            // 2. Tikuvchilar uchun hisoblash
            $sewingPayments = $this->calculateSewingPayments($data, $quantity);
            $payments = array_merge($payments, $sewingPayments);

            // 3. Group Master uchun hisoblash
            $masterPayments = $this->calculateGroupMasterPayments($data, $quantity);
            $payments = array_merge($payments, $masterPayments);

            // 4. Boshqa bo'limlar uchun hisoblash
            $otherPayments = $this->calculateOtherDepartments($data, $quantity);
            $payments = array_merge($payments, $otherPayments);

            // 5. Expense'lar uchun hisoblash
            $expensePayments = $this->calculateExpenses($data, $quantity);
            $payments = array_merge($payments, $expensePayments);

            // To'lovlarni saqlash
            foreach ($payments as $payment) {
                DB::table('daily_payments')->insert($payment);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'To\'lovlar muvaffaqiyatli hisoblandi',
                'payments_count' => count($payments),
                'total_amount' => array_sum(array_column($payments, 'calculated_amount'))
            ];

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Payment calculation error', [
                'order_submodel_id' => $orderSubmodelId,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'To\'lovlar hisoblashda xatolik: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Order ma'lumotlarini olish va filtrlash
     */
    private function getOrderData(int $orderSubmodelId)
    {
        $data = DB::table('order_sub_models as osm')
            ->join('order_models as om', 'osm.order_model_id', '=', 'om.id')
            ->join('orders as o', 'om.order_id', '=', 'o.id')
            ->join('models as m', 'om.model_id', '=', 'm.id')
            ->join('sub_models as sm', 'osm.submodel_id', '=', 'sm.id')
            ->leftJoin('order_groups as omg', 'osm.id', '=', 'omg.submodel_id')
            ->leftJoin('groups as g', 'omg.group_id', '=', 'g.id')
            ->select(
                'o.id as order_id',
                'o.price as order_price',
                'o.quantity as order_quantity',
                'o.branch_id as branch_id',
                'o.season_year as season_year',
                'o.season_type as season_type',
                'm.id as model_id',
                'm.minute as model_minute',
                'm.rasxod as model_rasxod',
                'g.id as group_id',
                'osm.id as order_submodel_id'
            )
            ->where('osm.id', $orderSubmodelId)
            ->first();

            // dd($data);

        // Filtrlarni tekshirish
        if (!$data) {
            return null;
        }

        // Faqat 2025 yil va Summer uchun ishlaydi
        if ($data->season_year != 2026 || strtolower($data->season_type) != 'summer') {
            Log::info('Payment calculation skipped - season filter', [
                'order_id' => $data->order_id,
                'season_year' => $data->season_year,
                'season_type' => $data->season_type,
                'required' => '2025 Summer'
            ]);
            return null;
        }

        return $data;
    }

    /**
     * 1. KESUVCHILAR uchun hisoblash
     * Model minute * department rasxod * quantity
     */
    private function calculateCuttingPayments($data, int $quantity): array
    {
        $payments = [];

        $cuttingDept = DB::table('departments')->where('name', 'ILIKE', 'kesuv')->first();
        if (!$cuttingDept) return $payments;

        $modelMinute = $data->model_minute ?? 0;
        $deptRasxod = $cuttingDept->rasxod ?? 0;
        
        // Umumiy summa: model daqiqasi * bo'lim rasxodi * soni
        $totalAmount = $modelMinute * $deptRasxod * $quantity;

        if ($totalAmount <= 0) return $payments;

        // Bu bo'limdagi ishchilarni olish
        $employees = DB::table('employees')
            ->where('department_id', $cuttingDept->id)
            ->where('percentage', '>', 0)
            ->where('status', 'active')
            ->get();

        foreach ($employees as $employee) {
            $employeeAmount = ($totalAmount * $employee->percentage) / 100;

            $payments[] = [
                'employee_id' => $employee->id,
                'model_id' => $data->model_id,
                'order_id' => $data->order_id,
                'department_id' => $cuttingDept->id,
                'payment_date' => now()->toDateString(),
                'quantity_produced' => $quantity,
                'calculated_amount' => round($employeeAmount, 2),
                'employee_percentage' => $employee->percentage,
                'created_at' => now()
            ];
        }

        return $payments;
    }

    /**
     * 2. TIKUVCHILAR uchun hisoblash
     * Order price'ning foizidan (department_budgets)
     */
    private function calculateSewingPayments($data, int $quantity): array
    {
        $payments = [];

        $sewingDept = DB::table('departments')->where('name', 'ILIKE', 'tikuv')->first();
        if (!$sewingDept) return $payments;

        // Department budget'dan foizni olish
        $deptBudget = DB::table('department_budgets')
            ->where('department_id', $sewingDept->id)
            ->first();

        if (!$deptBudget || $deptBudget->type !== 'percent') return $payments;

        // Order narxini so'mga o'tkazish
        $orderPriceInSum = $data->order_price * self::DEFAULT_DOLLAR_RATE;
        
        // Umumiy tikuv summasi: order narxining foizi * miqdor
        $totalSewingAmount = ($orderPriceInSum * $deptBudget->quantity / 100) * $quantity;

        if ($totalSewingAmount <= 0) return $payments;

        // Bugungi tikish natijalarini olish (group asosida)
        $todayOutputs = DB::table('sewing_outputs as so')
            ->join('order_sub_models as osm', 'so.order_submodel_id', '=', 'osm.id')
            ->where('osm.order_model_id', function($query) use ($data) {
                $query->select('order_model_id')
                    ->from('order_sub_models')
                    ->where('id', $data->order_submodel_id)
                    ->limit(1);
            })
            ->whereDate('so.created_at', now()->toDateString())
            ->select('osm.order_model_group_id', DB::raw('SUM(so.quantity) as total_sewn'))
            ->groupBy('osm.order_model_group_id')
            ->get()
            ->keyBy('order_model_group_id');

        // Har bir group uchun hisoblash
        foreach ($todayOutputs as $output) {
            $groupId = $output->order_model_group_id;
            $groupSewnQuantity = $output->total_sewn;

            // Group ishchilarini olish (faqat tikuvchilar, payment_type piece bo'lganlar)
            $groupEmployees = DB::table('employees as e')
                ->join('users as u', 'e.user_id', '=', 'u.id')
                ->where('e.group_id', $groupId)
                ->where('e.department_id', $sewingDept->id)
                ->where('e.percentage', '>', 0)
                ->where('e.status', 'active')
                ->where('e.payment_type', 'piece') // Faqat ishga qarab oluvchilar
                ->select('e.*')
                ->get();

            foreach ($groupEmployees as $employee) {
                // Ishchi tikkan nisbatda va o'z foizidan pul oladi
                $employeeShare = ($groupSewnQuantity / $quantity) * $totalSewingAmount;
                $employeeAmount = ($employeeShare * $employee->percentage) / 100;

                $payments[] = [
                    'employee_id' => $employee->id,
                    'model_id' => $data->model_id,
                    'order_id' => $data->order_id,
                    'department_id' => $sewingDept->id,
                    'payment_date' => now()->toDateString(),
                    'quantity_produced' => $groupSewnQuantity,
                    'calculated_amount' => round($employeeAmount, 2),
                    'employee_percentage' => $employee->percentage,
                    'created_at' => now()
                ];
            }

            // Universal ishchilar (payment_type = universal)
            $universalEmployees = DB::table('employees as e')
                ->join('users as u', 'e.user_id', '=', 'u.id')
                ->where('e.group_id', $groupId)
                ->where('e.department_id', $sewingDept->id)
                ->where('e.percentage', '>', 0)
                ->where('e.status', 'active')
                ->where('e.payment_type', 'universal')
                ->select('e.*')
                ->get();

            foreach ($universalEmployees as $employee) {
                $employeeShare = ($groupSewnQuantity / $quantity) * $totalSewingAmount;
                $employeeAmount = ($employeeShare * $employee->percentage) / 100;

                $payments[] = [
                    'employee_id' => $employee->id,
                    'model_id' => $data->model_id,
                    'order_id' => $data->order_id,
                    'department_id' => $sewingDept->id,
                    'payment_date' => now()->toDateString(),
                    'quantity_produced' => $groupSewnQuantity,
                    'calculated_amount' => round($employeeAmount, 2),
                    'employee_percentage' => $employee->percentage,
                    'created_at' => now()
                ];
            }
        }

        return $payments;
    }

    /**
     * 3. GROUP MASTER uchun hisoblash
     * Model minute * expense master rasxod * quantity
     */
    private function calculateGroupMasterPayments($data, int $quantity): array
    {
        $payments = [];

        if (!$data->group_id) return $payments;

        // Master uchun expense'dan rasxodini olish
        $masterExpense = DB::table('expenses')
            ->where('name', 'ILIKE', '%master%')
            ->first();

        if (!$masterExpense) return $payments;

        // Group Master'ni topish
        $groupMaster = DB::table('employees as e')
            ->join('users as u', 'e.user_id', '=', 'u.id')
            ->join('roles as r', 'u.role_id', '=', 'r.id')
            ->where('r.name', 'groupMaster')
            ->where('e.group_id', $data->group_id)
            ->where('e.status', 'active')
            ->select('e.*')
            ->first();

        if (!$groupMaster) return $payments;

        // Master model daqiqasiga expense'dagi rasxodni oladi
        $modelMinute = $data->model_minute ?? 0;
        $masterRasxod = $masterExpense->quantity ?? 0;
        $masterAmount = $modelMinute * $masterRasxod * $quantity;

        if ($masterAmount <= 0) return $payments;

        $payments[] = [
            'employee_id' => $groupMaster->id,
            'model_id' => $data->model_id,
            'order_id' => $data->order_id,
            'department_id' => $groupMaster->department_id,
            'payment_date' => now()->toDateString(),
            'quantity_produced' => $quantity,
            'calculated_amount' => round($masterAmount, 2),
            'employee_percentage' => 100,
            'created_at' => now()
        ];

        return $payments;
    }

    /**
     * 4. BOSHQA BO'LIMLAR uchun hisoblash
     * Model minute * department rasxod * quantity
     */
    private function calculateOtherDepartments($data, int $quantity): array
    {
        $payments = [];

        // Kesuv va Tikuvdan tashqari bo'limlar
       $departments = DB::table('departments')
       ->join('department_budgets as db', 'departments.id', '=', 'db.department_id')
        ->where('name', 'NOT ILIKE', '%kesuv%')
        ->where('name', 'NOT ILIKE', '%tikuv%')
        ->select('departments.*', 'db.quantity as rasxod')
        ->get();


        $modelMinute = $data->model_minute ?? 0;

        foreach ($departments as $dept) {
            // Har bir bo'lim uchun: model daqiqasi * bo'lim rasxodi * miqdor
            $totalAmount = $modelMinute * $dept->rasxod * $quantity;

            if ($totalAmount <= 0) continue;

            // Bo'lim ishchilarini olish
            $employees = DB::table('employees')
                ->where('department_id', $dept->id)
                ->where('percentage', '>', 0)
                ->where('status', 'active')
                ->get();

            foreach ($employees as $employee) {
                $employeeAmount = ($totalAmount * $employee->percentage) / 100;

                $payments[] = [
                    'employee_id' => $employee->id,
                    'model_id' => $data->model_id,
                    'order_id' => $data->order_id,
                    'department_id' => $dept->id,
                    'payment_date' => now()->toDateString(),
                    'quantity_produced' => $quantity,
                    'calculated_amount' => round($employeeAmount, 2),
                    'employee_percentage' => $employee->percentage,
                    'created_at' => now()
                ];
            }
        }

        return $payments;
    }

    /**
     * 5. EXPENSE'lar uchun hisoblash
     */
    private function calculateExpenses($data, int $quantity): array
    {
        $payments = [];

        // Master expense'dan tashqari boshqa xarajatlar
        $expenses = DB::table('expenses')
            ->where('name', 'NOT ILIKE', '%master%')
            ->get();

        foreach ($expenses as $expense) {
            $totalAmount = 0;

            if ($expense->type === 'sum') {
                // Model daqiqasi * expense quantity * order quantity
                $modelMinute = $data->model_minute ?? 0;
                $totalAmount = $modelMinute * $expense->quantity * $quantity;
            } elseif ($expense->type === 'percent') {
                // Order narxining foizidan
                $orderPriceInSum = $data->order_price * self::DEFAULT_DOLLAR_RATE;
                $totalAmount = ($orderPriceInSum * $expense->quantity / 100) * $quantity;
            }

            if ($totalAmount <= 0) continue;

            // Expense'ni alohida log qilish
            Log::info('Expense calculated', [
                'expense_name' => $expense->name,
                'order_id' => $data->order_id,
                'amount' => $totalAmount,
                'quantity' => $quantity
            ]);
        }

        return $payments;
    }

    /**
     * Oylik ishchilarni hisoblash (attendance asosida)
     */
    public function calculateMonthlySalaries(\DateTime $date): array
    {
        try {
            $payments = [];

            // payment_type = 'month' bo'lgan ishchilar
            $monthlyEmployees = DB::table('employees')
                ->where('payment_type', 'month')
                ->whereNotNull('salary')
                ->where('salary', '>', 0)
                ->where('status', 'active')
                ->get();

            foreach ($monthlyEmployees as $employee) {
                // Kelgan kunlarini tekshirish (agar attendance tizimi bo'lsa)
                $hasAttendance = DB::table('attendances')
                    ->where('employee_id', $employee->id)
                    ->whereDate('date', $date)
                    ->where('status', 'present')
                    ->exists();

                if ($hasAttendance) {
                    $dailyAmount = $employee->salary / 30;

                    $payments[] = [
                        'employee_id' => $employee->id,
                        'model_id' => null,
                        'order_id' => null,
                        'department_id' => $employee->department_id,
                        'payment_date' => $date->format('Y-m-d'),
                        'quantity_produced' => 0,
                        'calculated_amount' => round($dailyAmount, 2),
                        'employee_percentage' => 100,
                        'created_at' => now()
                    ];
                }
            }

            // Saqlash
            foreach ($payments as $payment) {
                DB::table('daily_payments')->insert($payment);
            }

            return [
                'success' => true,
                'message' => 'Oylik maoshlar hisoblandi',
                'payments_count' => count($payments)
            ];

        } catch (Exception $e) {
            Log::error('Monthly salary calculation error', [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ];
        }
    }
}