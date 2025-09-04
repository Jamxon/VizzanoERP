<?php

namespace App\Http\Controllers;

use App\Exports\DepartmentGroupsExport;
use App\Exports\GroupsOrdersEarningsExport;
use App\Http\Resources\GroupPlanResource;
use App\Models\Attendance;
use App\Models\Cashbox;
use App\Models\Employee;
use App\Models\Group;
use App\Models\CashboxBalance;
use App\Models\CashboxTransaction;
use App\Models\Currency;
use App\Models\MonthlyExpense;
use App\Models\Order;
use App\Models\SalaryPayment;
use App\Models\SewingOutputs;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CasherController extends Controller
{

    public function getMonthlyCost(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'dollar_rate' => 'nullable|numeric',
        ]);

        try {
            $start = $request->start_date
                ? Carbon::parse($request->start_date)->startOfDay()
                : Carbon::now()->startOfMonth();

            $end = $request->end_date
                ? Carbon::parse($request->end_date)->endOfDay()
                : Carbon::now()->endOfMonth();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid date format. Please use YYYY-MM-DD'], 400);
        }

        $dollarRate = $request->dollar_rate ?? 12900;

        $period = CarbonPeriod::create($start, $end);
        $orderSummaries = [];
        $monthlyStats = [
            'aup' => 0,
            'kpi' => 0,
            'transport_attendance' => 0,
            'tarification' => 0,
            'daily_expenses' => 0,
            'total_earned_uzs' => 0,
            'total_output_cost_uzs' => 0,
            'total_fixed_cost_uzs' => 0,
            'net_profit_uzs' => 0,
            'employee_count_sum' => 0,
            'total_output_quantity' => 0,
        ];

        foreach ($period as $dateObj) {
            $date = $dateObj->toDateString();

            $requestForDay = new Request([
                'date' => $date,
                'dollar_rate' => $dollarRate,
            ]);

            $daily = $this->getDailyCost($requestForDay)->getData(true);

            $monthlyStats['aup'] += $daily['aup'] ?? 0;
            $monthlyStats['kpi'] += $daily['kpi'] ?? 0;
            $monthlyStats['transport_attendance'] += $daily['transport_attendance'] ?? 0;
            $monthlyStats['tarification'] += $daily['tarification'] ?? 0;
            $monthlyStats['daily_expenses'] += $daily['daily_expenses'] ?? 0;
            $monthlyStats['total_earned_uzs'] += $daily['total_earned_uzs'] ?? 0;
            $monthlyStats['total_output_cost_uzs'] += isset($daily['orders']) ? array_sum(array_column($daily['orders'], 'total_output_cost_uzs')) : 0;
            $monthlyStats['total_fixed_cost_uzs'] += $daily['total_fixed_cost_uzs'] ?? 0;
            $monthlyStats['net_profit_uzs'] += $daily['net_profit_uzs'] ?? 0;
            $monthlyStats['employee_count_sum'] += $daily['employee_count'] ?? 0;
            $monthlyStats['total_output_quantity'] += $daily['total_output_quantity'] ?? 0;

            if (!isset($daily['orders'])) continue;

            foreach ($daily['orders'] as $order) {
                $orderId = $order['order']['id'] ?? null;
                if (!$orderId) continue;

                if (!isset($orderSummaries[$orderId])) {
                    $orderSummaries[$orderId] = $order;
                    $orderSummaries[$orderId]['total_earned_uzs'] = $order['total_output_cost_uzs']; // initial
                    continue;
                }

                $orderSummaries[$orderId]['total_quantity'] += $order['total_quantity'];
                $orderSummaries[$orderId]['rasxod_limit_uzs'] += $order['rasxod_limit_uzs'];
                $orderSummaries[$orderId]['bonus'] += $order['bonus'];
                $orderSummaries[$orderId]['tarification'] += $order['tarification'];
                $orderSummaries[$orderId]['total_earned_uzs'] += $order['total_output_cost_uzs'];
                $orderSummaries[$orderId]['total_output_cost_uzs'] += $order['total_output_cost_uzs'];
                $orderSummaries[$orderId]['total_fixed_cost_uzs'] += $order['total_fixed_cost_uzs'];
                $orderSummaries[$orderId]['net_profit_uzs'] += $order['net_profit_uzs'];

                foreach ($order['costs_uzs'] as $key => $val) {
                    if (!isset($orderSummaries[$orderId]['costs_uzs'][$key])) {
                        $orderSummaries[$orderId]['costs_uzs'][$key] = 0;
                    }
                    $orderSummaries[$orderId]['costs_uzs'][$key] += $val;
                }
            }
        }

        foreach ($orderSummaries as &$order) {
            $qty = max($order['total_quantity'], 1);
            $totalCost = $order['total_fixed_cost_uzs'];
            $earned = $order['total_earned_uzs'];

            $order['cost_per_unit_uzs'] = round($totalCost / $qty);
            $order['profit_per_unit_uzs'] = round(($earned / $qty) - $order['cost_per_unit_uzs']);
            $order['profitability_percent'] = $totalCost > 0
                ? round(($order['net_profit_uzs'] / $totalCost) * 100, 2)
                : null;
        }

        $workingDays = collect($period)->filter(fn($date) => !$date->isSunday())->count();
        $averageEmployeeCount = round($monthlyStats['employee_count_sum'] / max($workingDays, 1));
        $perEmployeeCost = $monthlyStats['total_fixed_cost_uzs'] / max(1, $monthlyStats['employee_count_sum']);

        // Umumiy quantity va har bir dona uchun xarajat hisoblash
        $costPerUnitOverall = $monthlyStats['total_output_quantity'] > 0
            ? $monthlyStats['total_fixed_cost_uzs'] / $monthlyStats['total_output_quantity']
            : 0;

        return response()->json([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days_in_period' => count($period),
            'dollar_rate' => $dollarRate,
            'aup' => $monthlyStats['aup'],
            'kpi' => $monthlyStats['kpi'],
            'transport_attendance' => $monthlyStats['transport_attendance'],
            'tarification' => $monthlyStats['tarification'],
            'monthly_expenses' => $monthlyStats['daily_expenses'],
            'total_earned_uzs' => $monthlyStats['total_earned_uzs'],
            'total_output_cost_uzs' => $monthlyStats['total_output_cost_uzs'],
            'total_fixed_cost_uzs' => $monthlyStats['total_fixed_cost_uzs'],
            'net_profit_uzs' => $monthlyStats['net_profit_uzs'],
            'average_employee_count' => $averageEmployeeCount,
            'per_employee_cost_uzs' => $perEmployeeCost,
            'orders' => array_values($orderSummaries),

            // Yangi qo'shilgan qismlar
            'total_output_quantity' => $monthlyStats['total_output_quantity'],
            'cost_per_unit_overall_uzs' => round($costPerUnitOverall, 2),
        ]);
    }

    public function getDailyCost(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = $request->date ?? Carbon::today()->toDateString();
        $dollarRate = $request->dollar_rate ?? 12900;
        $branchId = auth()->user()->employee->branch_id;

        $carbonDate = Carbon::parse($date);
        $daysInMonth = $carbonDate->daysInMonth;

        // Shu sanadagi barcha ishchilarning IDlari
        $relatedEmployeeIds = DB::table('employee_tarification_logs')
            ->whereDate('date', $date)
            ->whereIn('employee_id', Employee::where('branch_id', $branchId)->pluck('id'))
            ->pluck('employee_id')
            ->unique()
            ->values();

        // Transport
        $transport = DB::table('transport_attendance')
            ->join('transport', 'transport_attendance.transport_id', '=', 'transport.id')
            ->whereDate('transport_attendance.date', $date)
            ->where('transport.branch_id', $branchId)
            ->sum(DB::raw('(transport.salary + transport.fuel_bonus) * transport_attendance.attendance_type'));

        // Monthly expenses ni type bo'yicha ajratish
        $monthlyExpenses = DB::table('monthly_expenses')
            ->whereMonth('month', $carbonDate->month)
            ->whereYear('month', $carbonDate->year)
            ->where('branch_id', $branchId)
            ->get();

        // Type = 'monthly' bo'lgan xarajatlarni kuniga bo'lib hisoblash
        $dailyExpenseMonthly = $monthlyExpenses
                ->where('type', 'monthly')
                ->sum('amount') / $daysInMonth;

        $thisBranchEmployeeIds = Employee::where('branch_id', $branchId)->pluck('id');

        $aup = DB::table('attendance_salary')
            ->whereDate('date', $date)
            ->whereIn('employee_id', $thisBranchEmployeeIds)
            ->sum('amount');

        $employees = Attendance::whereDate('date', $date)
            ->where('status', 'present')
            ->whereHas('employee', function ($q) use ($branchId)  {
                $q->where('status', '!=', 'kicked');
                $q->where('branch_id', $branchId);
            })->count();

        $outputs = SewingOutputs::with([
            'orderSubmodel.orderModel.order',
            'orderSubmodel.orderModel.model',
            'orderSubmodel.orderModel.submodels.submodel'
        ])
            ->whereDate('created_at', $date)
            ->whereHas('orderSubmodel.orderModel.order', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
//            ->whereHas('orderSubmodel.group.group', function ($q) {
//                $q->where('name', 'not like', '%Трикотаж%'); // '!=' emas, 'not like'
//            })
            ->get();

        $grouped = $outputs->groupBy(fn($item) => optional($item->orderSubmodel->orderModel)->order_id);
        $totalOutputQty = $outputs->sum('quantity');

        $orders = $grouped->map(function ($items) use (
            $dollarRate, $date, $relatedEmployeeIds,
            $dailyExpenseMonthly, $transport, $aup, $totalOutputQty, $monthlyExpenses
        ) {
            $first = $items->first();
            $orderModel = optional($first->orderSubmodel)->orderModel;
            $order = optional($orderModel)->order;
            $orderId = $order->id ?? null;

            $totalQty = $items->sum('quantity');
            $priceUSD = $order->price ?? 0;
            $priceUZS = $priceUSD * $dollarRate;
            $remainder = ($orderModel->rasxod ?? 0) * $totalQty;

            $bonus = DB::table('bonuses')
                ->whereDate('created_at', $date)
                ->where('order_id', $orderId)
                ->sum('amount');

            $tarification = DB::table('employee_tarification_logs')
                ->join('tarifications', 'employee_tarification_logs.tarification_id', '=', 'tarifications.id')
                ->join('tarification_categories', 'tarifications.tarification_category_id', '=', 'tarification_categories.id')
                ->join('order_sub_models', 'tarification_categories.submodel_id', '=', 'order_sub_models.id')
                ->join('order_models', 'order_sub_models.order_model_id', '=', 'order_models.id')
                ->join('orders', 'order_models.order_id', '=', 'orders.id')
                ->whereDate('employee_tarification_logs.date', $date)
                ->where('orders.id', $orderId)
                ->sum('employee_tarification_logs.amount_earned');

            // Type bo'yicha xarajatlarni hisoblash
            $orderShareRatio = $totalOutputQty > 0 ? $totalQty / $totalOutputQty : 0;

            // Monthly type xarajat
            $allocatedMonthlyExpenseMonthly = $dailyExpenseMonthly * $orderShareRatio;

            // Income percentage type xarajat
            $incomePercentageExpense = 0;
            $incomePercentageExpenses = $monthlyExpenses->where('type', 'income_percentage');
            foreach ($incomePercentageExpenses as $expense) {
                $percentageAmount = ($priceUZS * $totalQty) * ($expense->amount / 100);
                $incomePercentageExpense += $percentageAmount;
            }

            // Amortization type xarajat (har bir mahsulot uchun 10 sent)
            $amortizationExpense = 0;
            $amortizationExpenses = $monthlyExpenses->where('type', 'amortization');
            if ($amortizationExpenses->count() > 0) {
                $amortizationExpense = $totalQty * 0.10 * $dollarRate;
            }

            $fixedCost = $bonus + $remainder;

            $allocatedTransport = $transport * $orderShareRatio;
            $allocatedAup = $aup * $orderShareRatio;

            $totalExtra = $allocatedTransport + $allocatedAup + $allocatedMonthlyExpenseMonthly + $incomePercentageExpense + $amortizationExpense;

            $perUnitCost = $totalQty > 0 ? ($fixedCost + $totalExtra) / $totalQty : 0;
            $profitUZS = ($priceUZS * $totalQty) - ($fixedCost + $totalExtra);

            $responsibleUsers = $orderModel->submodels->map(function ($submodel) {
                return optional($submodel->group->group)->responsibleUser;
            })->filter()->unique('id')->values();

            $rasxodPercentOfPrice = $priceUZS > 0
                ? round((($orderModel->rasxod ?? 0) / $priceUZS) * 100, 2)
                : null;

            return [
                'order' => $order,
                'responsibleUser' => $responsibleUsers,
                'model' => $orderModel->model ?? null,
                'submodels' => $orderModel->submodels->pluck('submodel')->filter()->values(),
                'price_usd' => $priceUSD,
                'price_uzs' => $priceUZS,
                'total_quantity' => $totalQty,
                'rasxod_limit_uzs' => $remainder,
                'rasxod_percent_of_price' => $rasxodPercentOfPrice,
                'bonus' => $bonus,
                'tarification' => $tarification,
                'total_output_cost_uzs' => $priceUSD * $totalQty * $dollarRate,
                'costs_uzs' => compact('bonus', 'remainder', 'allocatedTransport', 'allocatedAup', 'allocatedMonthlyExpenseMonthly', 'incomePercentageExpense', 'amortizationExpense'),
                'total_fixed_cost_uzs' => $fixedCost + $totalExtra,
                'net_profit_uzs' => $profitUZS,
                'cost_per_unit_uzs' => round($perUnitCost),
                'profit_per_unit_uzs' => round(($priceUZS - $perUnitCost)),
                'profitability_percent' => ($fixedCost + $totalExtra) > 0
                    ? round(($profitUZS / ($fixedCost + $totalExtra)) * 100, 2)
                    : null,
            ];
        })->values();

        $totalEarned = $orders->sum('total_output_cost_uzs');
        $totalFixedCost = $totalEarned - $orders->sum('net_profit_uzs');

        // Umumiy xarajatlarni hisoblash
        $totalIncomePercentageExpense = $orders->sum('costs_uzs.incomePercentageExpense');
        $totalAmortizationExpense = $orders->sum('costs_uzs.amortizationExpense');

        $dailyExpense = $dailyExpenseMonthly + $totalIncomePercentageExpense + $totalAmortizationExpense;

        // Umumiy quantity va har bir dona uchun xarajat hisoblash
        $costPerUnitOverall = $totalOutputQty > 0 ? $totalFixedCost / $totalOutputQty : 0;

        return response()->json([
            'date' => $date,
            'dollar_rate' => $dollarRate,
            'orders' => $orders,
            'transport_attendance' => $transport,
            'daily_expenses' => $dailyExpense,
            'aup' => $aup,
            'total_earned_uzs' => $totalEarned,
            'total_fixed_cost_uzs' => $totalFixedCost,
            'employee_count' => $employees,
            'per_employee_cost_uzs' => $totalFixedCost / max($employees, 1),
            'net_profit_uzs' => $totalEarned - $totalFixedCost,
            'kpi' => DB::table('bonuses')->whereDate('created_at', $date)->sum('amount'),
            'tarification' => DB::table('employee_tarification_logs')
                ->join('tarifications', 'employee_tarification_logs.tarification_id', '=', 'tarifications.id')
                ->join('tarification_categories', 'tarifications.tarification_category_id', '=', 'tarification_categories.id')
                ->join('order_sub_models', 'tarification_categories.submodel_id', '=', 'order_sub_models.id')
                ->join('order_models', 'order_sub_models.order_model_id', '=', 'order_models.id')
                ->join('orders', 'order_models.order_id', '=', 'orders.id')
                ->whereDate('employee_tarification_logs.date', $date)
                ->whereIn('employee_tarification_logs.employee_id', $relatedEmployeeIds)
                ->sum('employee_tarification_logs.amount_earned'),

            // Yangi qo'shilgan qismlar
            'total_output_quantity' => $totalOutputQty,
            'cost_per_unit_overall_uzs' => round($costPerUnitOverall, 2),
        ]);
    }

    public function getMonthlyExpense(Request $request): \Illuminate\Http\JsonResponse
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $expenses = MonthlyExpense::whereMonth('month', Carbon::parse($month)->month)
            ->whereYear('month', Carbon::parse($month)->year)
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->get();

        if ($expenses->isEmpty()) {
            return response()->json(
                [
                    'month' => $month,
                'expenses' => []
                ],
            );
        }

        return response()->json([
            'month' => $month,
            'expenses' => $expenses->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'name' => $expense->name,
                    'type' => $expense->type,
                    'amount' => $expense->amount,
                    'date' => $expense->month,
                ];
            }),
        ]);
    }

    public function storeMonthlyExpense(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'month' => 'required|date_format:Y-m',
            'name' => 'required|string|max:255',
        ]);

        $expense = MonthlyExpense::create([
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'month' => $validated['month'] . '-01',
            'name' => $validated['name'],
            'branch_id' => auth()->user()->employee->branch_id,
        ]);

        return response()->json(['message' => 'Saved', 'data' => $expense]);
    }

    public function editMonthlyExpense($id, Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'type' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'month' => 'nullable|date_format:Y-m',
            'name' => 'nullable|string|max:255',
        ]);
        $expense = MonthlyExpense::findOrFail($id);

        if (isset($validated['month'])) {
            $validated['month'] .= '-01'; // 2025-06 => 2025-06-01
        }

        $expense->update($validated);

        return response()->json(['message' => 'Updated', 'data' => $expense]);
    }

    public function exportGroupsByDepartmentIdPdf(Request $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $departmentId = $request->input('department_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $group_id = $request->input('group_id');
        $orderIds = $request->input('order_ids', []); // ✅ order_ids qo‘shdik

        if (!$departmentId) {
            return response()->json(['message' => '❌ department_id kiritilmadi.'], 422);
        }

        $groupQuery = Group::where('department_id', $departmentId)
            ->with(['employees' => function ($query) {
                $query->select('id', 'name', 'position_id', 'group_id', 'balance', 'payment_type', 'status', 'type')
                    ->where('type', '!=', 'aup')
                    ->with('salaryPayments');
            }]);

        if (!empty($group_id)) {
            $groupQuery->where('id', $group_id);
        }

        $groups = $groupQuery->get();

        $result = $groups->map(function ($group) use ($startDate, $endDate, $orderIds) {
            $employees = $group->employees
                ->map(fn($employee) => $this->getEmployeeEarnings($employee, $startDate, $endDate, $orderIds)) // ✅ orderIds qo‘shildi
                ->filter(function ($employeeData) {
                    if (!$employeeData) {
                        return false;
                    }
                    if (
                        ($employeeData['total_earned'] ?? 0) == 0 &&
                        ($employeeData['balance'] ?? 0) == 0
                    ) {
                        return false;
                    }
                    return true;
                })
                ->sortBy(fn($e) => mb_strtolower($e['name'] ?? ''))
                ->values();

            $groupTotal = $employees->sum(fn($e) => $e['balance'] ?? 0);

            return [
                'id' => $group->id,
                'name' => $group->name,
                'total_balance' => $groupTotal,
                'employees' => $employees->values()->toArray(),
            ];
        })->values()->toArray();

        /*
        // Guruhsiz xodimlar (hozircha jo‘natilmaydi)
        $ungroupedEmployees = Employee::where('department_id', $departmentId)
            ->whereNull('group_id')
            ->where('type', '!=', 'aup')
            ->select('id', 'name', 'group_id', 'position_id', 'balance', 'payment_type', 'status', 'type')
            ->with('salaryPayments')
            ->get()
            ->map(fn($employee) => $this->getEmployeeEarnings($employee, $startDate, $endDate, $orderIds))
            ->filter(function ($employeeData) {
                if (!$employeeData) {
                    return false;
                }
                if (
                    ($employeeData['total_earned'] ?? 0) == 0 &&
                    ($employeeData['balance'] ?? 0) == 0
                ) {
                    return false;
                }
                return true;
            })
            ->sortBy(fn($e) => mb_strtolower($e['name'] ?? ''))
            ->values();

        if ($ungroupedEmployees->isNotEmpty()) {
            $ungroupedTotal = $ungroupedEmployees->sum(fn($e) => $e['balance'] ?? 0);
            $result[] = [
                'id' => null,
                'name' => 'Guruhsiz',
                'total_balance' => $ungroupedTotal,
                'employees' => $ungroupedEmployees->values()->toArray(),
            ];
        }
        */

        // PDF yasash
        $pdf = Pdf::loadView('pdf.group-salary-report', [
            'data' => $result,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('group-salary-report.pdf');
    }

    public function giveSalaryOrAdvance(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'amount' => 'required|numeric',
                'month' => 'required|date_format:Y-m',
                'type' => 'required|in:salary,advance',
                'comment' => 'nullable|string',
            ]);

            $validated['month'] = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();

            $employee = Employee::findOrFail($validated['employee_id']);

            $cashboxBalance = CashboxBalance::with('cashbox')
                ->whereHas('cashbox', function ($q) {
                    $q->where('branch_id', auth()->user()->employee->branch_id);
                })
                ->whereHas('currency', function ($q) {
                    $q->where('name', "So'm");
                })
                ->first();

            if (!$cashboxBalance) {
                throw new \Exception('So‘mda ishlovchi cashbox topilmadi.');
            }

            $cashboxId = $cashboxBalance->cashbox_id;
            $currency = Currency::where('name', "So'm")->first();

            // 🔎 Eski paymentni tekshirish
            $existingPayment = SalaryPayment::where([
                'employee_id' => $validated['employee_id'],
                'month' => $validated['month'],
                'type' => $validated['type'],
            ])->first();

            $oldAmount = $existingPayment?->amount ?? 0;

            // 🔄 Payment update/create
            $payment = SalaryPayment::updateOrCreate(
                [
                    'employee_id' => $validated['employee_id'],
                    'month' => $validated['month'],
                    'type' => $validated['type'],
                ],
                [
                    'amount' => $validated['amount'],
                    'comment' => $validated['comment'] ?? null,
                ]
            );

            // 🔄 Transaction update/create
            $transactionData = [
                'cashbox_id' => $cashboxId,
                'currency_id' => $currency->id,
                'type' => 'expense',
                'amount' => $validated['amount'],
                'date' => now()->toDateString(),
                'source' => null,
                'destination_id' => $employee->id,
                'via_id' => auth()->user()->employee->id,
                'purpose' => $validated['type'] === 'advance' ? 'Avans to‘lovi' : 'Oylik to‘lovi',
                'comment' => $validated['comment'] ?? null,
                'target_cashbox_id' => null,
                'exchange_rate' => null,
                'target_amount' => null,
                'branch_id' => auth()->user()->employee->branch_id,
            ];

            if ($existingPayment) {
                // eski transactionni topib yangilash
                $existingTransaction = CashboxTransaction::where([
                    'destination_id' => $employee->id,
                    'purpose' => $transactionData['purpose'],
                    'amount' => $oldAmount,
                    'cashbox_id' => $cashboxId,
                    'currency_id' => $currency->id,
                ])->first();

                if ($existingTransaction) {
                    $existingTransaction->update($transactionData);
                } else {
                    CashboxTransaction::create($transactionData);
                }
            } else {
                CashboxTransaction::create($transactionData);
            }

            // 💰 Balans farqni hisoblash
            $difference = $validated['amount'] - $oldAmount;

            if ($difference !== 0) {
                $employee->decrement('balance', $difference);
                $cashboxBalance->decrement('amount', $difference);
            }

            return $payment;
        });
    }

    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {

        $orders = \App\Models\Order::with(['orderModel.submodels.submodel', 'orderModel.model'])
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->get();

        return response()->json($orders);
    }

    public function getGroupsByDepartmentId(Request $request): \Illuminate\Http\JsonResponse
    {
        $departmentId = $request->input('department_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $group_id = $request->input('group_id');
        $orderIds = $request->input('order_ids', []); // array
        $type = $request->input('type'); // normal yoki aup

        if (!$departmentId) {
            return response()->json(['message' => '❌ department_id kiritilmadi.'], 422);
        }

        // Guruhlarni olish (xodimlar bilan birga salaryPayments ham yuklaymiz)
        $groupQuery = Group::where('department_id', $departmentId)
            ->with(['employees' => function ($query) use ($type) {
                $query->select('id', 'name', 'position_id', 'group_id', 'salary', 'balance', 'payment_type', 'status')
                    ->with('salaryPayments');
                if ($type === 'aup') {
                    $query->where('type', 'aup');
                } else {
                    $query->where('type', '!=', 'aup');
                }
            }]);

        if (!empty($group_id)) {
            $groupQuery->where('id', $group_id);
        }

        $groups = $groupQuery->get();

        $result = $groups->map(function ($group) use ($startDate, $endDate, $orderIds) {
            $employees = $group->employees
                ->map(function ($employee) use ($startDate, $endDate, $orderIds) {
                    return $this->getEmployeeEarnings($employee, $startDate, $endDate, $orderIds);
                })
                ->filter();

            $groupTotal = $employees->sum(fn($e) => $e['balance'] ?? 0);

            return [
                'id' => $group->id,
                'name' => $group->name,
                'total_balance' => $groupTotal,
                'employees' => $employees->values()->toArray(),
            ];
        })->values()->toArray();

        // Guruhsiz xodimlarni olish (hozircha jo‘natilmaydi)
        $ungroupedEmployees = Employee::where('department_id', $departmentId)
            ->whereNull('group_id')
            ->where('type', $type === 'aup' ? 'aup' : '!=', 'aup')
            ->select('id', 'name', 'group_id', 'position_id', 'balance', 'salary', 'payment_type', 'status')
            ->with('salaryPayments')
            ->get()
            ->map(function ($employee) use ($startDate, $endDate, $orderIds) {
                return $this->getEmployeeEarnings($employee, $startDate, $endDate, $orderIds);
            })
            ->filter();

        if ($ungroupedEmployees->isNotEmpty()) {
            $ungroupedTotal = $ungroupedEmployees->sum(fn($e) => $e['balance'] ?? 0);

            $result[] = [
                'id' => null,
                'name' => 'Guruhsiz',
                'total_balance' => $ungroupedTotal,
                'employees' => $ungroupedEmployees->values()->toArray(),
            ];
        }

        return response()->json($result);
    }

    //2-usul

    public function getGroupsOrdersEarnings(Request $request): \Illuminate\Http\JsonResponse
    {
        $departmentId = $request->input('department_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $groupId = $request->input('group_id');
        $addOrderIds = $request->input('add', []);   // qo‘shiladigan order_id lar
        $minusOrderIds = $request->input('minus', []); // ayiriladigan order_id lar
        $type = $request->input('type'); // normal yoki aup

        if (!$departmentId) {
            return response()->json(['message' => '❌ department_id kiritilmadi.'], 422);
        }

        $groupQuery = Group::where('department_id', $departmentId)
            ->with(['employees' => function ($query) use ($type) {
                $query->select('id', 'name', 'position_id', 'group_id', 'salary', 'balance', 'payment_type', 'status');
                if ($type === 'aup') {
                    $query->where('type', 'aup');
                } else {
                    $query->where('type', '!=', 'aup');
                }
            }]);

        if (!empty($groupId)) {
            $groupQuery->where('id', $groupId);
        }

        $groups = $groupQuery->get();

        $result = $groups->map(function ($group) use ($startDate, $endDate, $addOrderIds, $minusOrderIds) {
            $employees = $group->employees
                ->map(function ($employee) use ($startDate, $endDate, $addOrderIds, $minusOrderIds) {

                    $employee->loadMissing(['position', 'group']);

                    /**
                     * AttendanceSalary (oylikchilar uchun)
                     */
                    $attendanceQuery = $employee->attendanceSalaries();
                    if ($startDate && $endDate) {
                        $attendanceQuery->whereBetween('date', [$startDate, $endDate]);
                    }
                    $attendanceTotal = $attendanceQuery->sum('amount');
                    $attendanceDays = $attendanceQuery->count();

                    /**
                     * EmployeeSalary (oy/bonus tarzida kiritilgan summalar)
                     */
                    $employeeSalaryQuery = $employee->employeeSalaries();
                    if ($startDate && $endDate) {
                        $start = \Carbon\Carbon::parse($startDate);
                        $end = \Carbon\Carbon::parse($endDate);

                        $employeeSalaryQuery->where(function ($q) use ($start, $end) {
                            $q->whereBetween('year', [$start->year, $end->year])
                                ->where(function ($q) use ($start, $end) {
                                    $q->where(function ($q) use ($start) {
                                        $q->where('year', $start->year)
                                            ->where('month', '>=', $start->month);
                                    })
                                        ->orWhere(function ($q) use ($end) {
                                            $q->where('year', $end->year)
                                                ->where('month', '<=', $end->month);
                                        })
                                        ->orWhere(function ($q) use ($start, $end) {
                                            $q->whereBetween('year', [$start->year + 1, $end->year - 1]);
                                        });
                                });
                        });
                    }
                    $employeeSalaryTotal = $employeeSalaryQuery->sum('amount');

                    /**
                     * TarificationLogs (piece_work uchun)
                     */
                    $logsQuery = $employee->employeeTarificationLogs()
                        ->with('tarification.tarificationCategory.submodel.orderModel.order');

//                    if ($startDate && $endDate) {
//                        $logsQuery->whereBetween('date', [$startDate, $endDate]);
//                    }

                    $logs = $logsQuery->get();

                    $orders = $logs->map(function ($log) {
                        return $log->tarification?->tarificationCategory?->submodel?->orderModel?->order;
                    })->filter()->unique('id');

                    // Qo‘shimcha orderlarni qo‘shish
                    if (!empty($addOrderIds)) {
                        $extraOrders = Order::whereIn('id', $addOrderIds)->get();
                        $orders = $orders->merge($extraOrders)->unique('id');
                    }

                    // Minus orderlarni chiqarib tashlash
                    if (!empty($minusOrderIds)) {
                        $orders = $orders->reject(fn($o) => in_array($o->id, $minusOrderIds));
                    }

                    $tarificationTotal = $logs->sum('amount_earned');

                    /**
                     * Total earned hisoblash
                     */
                    if ($employee->payment_type === 'piece_work') {
                        $totalEarned = $employeeSalaryTotal + $tarificationTotal;
                    } else {
                        $totalEarned = $attendanceTotal + $employeeSalaryTotal;
                    }

                    /**
                     * Paid salaries
                     */
                    $paidQuery = $employee->salaryPayments();
                    if ($startDate && $endDate) {
                        $paidQuery->whereBetween('month', [$startDate, $endDate]);
                    }
                    $paymentsGrouped = $paidQuery->get()->groupBy('type');

                    $paidAmountsByType = [];
                    $paidTotal = 0;

                    foreach ($paymentsGrouped as $type => $payments) {
                        $paidAmountsByType[$type] = $payments->map(function ($payment) use (&$paidTotal) {
                            $paidTotal += (float) $payment->amount;
                            return [
                                'amount' => (float) $payment->amount,
                                'date' => $payment->date,
                                'comment' => $payment->comment,
                                'month' => $payment->month->format('Y-m'),
                            ];
                        })->values();
                    }

                    return [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'position' => $employee->position->name ?? 'N/A',
                        'group' => optional($employee->group)->name ?? 'N/A',
                        'balance' => (float) $employee->balance,
                        'payment_type' => $employee->payment_type,
                        'salary' => (float) $employee->salary,

                        'attendance_salary' => $attendanceTotal,
                        'attendance_days' => $attendanceDays,
                        'employee_salary' => $employeeSalaryTotal,
                        'tarification_salary' => $tarificationTotal,
                        'total_earned' => $totalEarned,

                        'paid_amounts' => $paidAmountsByType,
                        'total_paid' => round($paidTotal, 2),
                        'net_balance' => round($totalEarned - $paidTotal, 2),

                        'orders' => $orders->pluck('id')->values(),
                    ];
                })
                ->filter();

            $groupTotal = $employees->sum(fn($e) => $e['total_earned'] ?? 0);

            return [
                'id' => $group->id,
                'name' => $group->name,
                'total_balance' => $groupTotal,
                'employees' => $employees->values()->toArray(),
            ];
        })->values()->toArray();

        return response()->json($result);
    }

    //2-usul excel

    public function exportGroupsOrdersEarnings(Request $request)
    {
        // Avval xuddi getGroupsOrdersEarnings dagi $result ni hisoblaymiz
        $data = $this->getGroupsOrdersEarnings($request)->getData(true);

        // Excel export qilish
        $fileName = 'groups_orders_earnings_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new GroupsOrdersEarningsExport($data), $fileName);
    }

    /**
     * $employee — Employee eloquent modeli (salaryPayments eager-load qilingan bo‘lishi mumkin)
     * $startDate, $endDate — 'Y-m-d' formatdagi string (yoki null)
     */
    public function getEmployeeEarnings($employee, $startDate, $endDate, $orderIds = [])
    {
        if ($employee->status === 'kicked' && ((float) $employee->balance) === 0.0) {
            return null;
        }

        $employee->loadMissing(['position', 'branch', 'group']);

        // AttendanceSalary (oylikchilar uchun)
        $attendanceQuery = $employee->attendanceSalaries();
        if ($startDate && $endDate) {
            $attendanceQuery->whereBetween('date', [$startDate, $endDate]);
        }
        $attendanceTotal = $attendanceQuery->sum('amount');
        $attendanceDays = $attendanceQuery->count();

        // EmployeeSalary
        $employeeSalaryQuery = $employee->employeeSalaries();
        if ($startDate && $endDate) {
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);

            $employeeSalaryQuery->where(function ($q) use ($start, $end) {
                $q->whereBetween('year', [$start->year, $end->year])
                    ->where(function ($q) use ($start, $end) {
                        $q->where(function ($q) use ($start) {
                            $q->where('year', $start->year)
                                ->where('month', '>=', $start->month);
                        })
                            ->orWhere(function ($q) use ($end) {
                                $q->where('year', $end->year)
                                    ->where('month', '<=', $end->month);
                            })
                            ->orWhere(function ($q) use ($start, $end) {
                                $q->whereBetween('year', [$start->year + 1, $end->year - 1]);
                            });
                    });
            });
        }
        $employeeSalaryTotal = $employeeSalaryQuery->sum('amount');

        // TarificationLogs (piece_work uchun)
        $tarificationQuery = $employee->employeeTarificationLogs();

        if (!empty($orderIds)) {
            $tarificationIds = \App\Models\Order::whereIn('id', $orderIds)
                ->with('orderModel.submodels.tarificationCategories.tarifications:id,tarification_category_id')
                ->get()
                ->flatMap(function ($order) {
                    return optional($order->orderModel)->submodels?->flatMap(function ($submodel) {
                        return optional($submodel->tarificationCategories)->flatMap(function ($category) {
                            return optional($category->tarifications)->pluck('id');
                        }) ?? collect();
                    }) ?? collect();
                })
                ->unique()
                ->values();

            if ($tarificationIds->isNotEmpty()) {
                $tarificationQuery->whereIn('tarification_id', $tarificationIds);
            } else {
                return null;
            }
        } elseif ($startDate && $endDate) {
            $tarificationQuery->whereBetween('date', [$startDate, $endDate]);
        }

        $tarificationTotal = $tarificationQuery->sum('amount_earned');

        // Umumiy hisob
        if ($employee->payment_type === 'piece_work') {
            $totalEarned = $employeeSalaryTotal + $tarificationTotal;
        } else {
            $totalEarned = $attendanceTotal + $employeeSalaryTotal;
        }

        // To'lovlar
        $paidQuery = $employee->salaryPayments();
        if ($startDate && $endDate) {
            $paidQuery->whereBetween('month', [$startDate, $endDate]);
        }
        $paymentsGrouped = $paidQuery->get()->groupBy('type');

        $paidAmountsByType = [];
        $paidTotal = 0;

        foreach ($paymentsGrouped as $type => $payments) {
            $paidAmountsByType[$type] = $payments->map(function ($payment) use (&$paidTotal) {
                $paidTotal += (float) $payment->amount;
                return [
                    'amount' => (float) $payment->amount,
                    'date' => $payment->date,
                    'comment' => $payment->comment,
                    'month' => $payment->month->format('Y-m'),
                ];
            })->values();
        }

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'position' => $employee->position->name ?? 'N/A',
            'group' => optional($employee->group)->name ?? 'N/A',
            'balance' => (float) $employee->balance,
            'payment_type' => $employee->payment_type,
            'salary' => (float) $employee->salary,

            'attendance_salary' => $attendanceTotal,
            'attendance_days' => $attendanceDays,
            'employee_salary' => $employeeSalaryTotal,
            'tarification_salary' => $tarificationTotal,
            'total_earned' => $totalEarned,

            'paid_amounts' => $paidAmountsByType,
            'total_paid' => round($paidTotal, 2),
            'net_balance' => round($totalEarned - $paidTotal, 2),
        ];
    }

    public function getSource(): \Illuminate\Http\JsonResponse
    {
        $sources = \App\Models\IncomeSource::all();
        return response()->json($sources);
    }

    public function getVia(): \Illuminate\Http\JsonResponse
    {
        $via = \App\Models\IncomeVia::all();
        return response()->json($via);
    }

    public function getDestination(): \Illuminate\Http\JsonResponse
    {
        $destinations = \App\Models\IncomeDestination::all();
        return response()->json($destinations);
    }

    public function storeIncome(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'source' => 'nullable|string|max:255',
//            'via_id' => 'required|exists:employees,id',
            'comment' => 'nullable|string|max:1000',
            'date' => 'nullable|date',
            'purpose' => 'nullable|string|max:1000',
        ]);

        try {
            $data['type'] = 'income';
            $data['date'] = $data['date'] ?? now()->toDateString();
            $data['branch_id'] = auth()->user()->employee->branch_id;

            $data['via_id'] = auth()->user()->employee->id;

            DB::transaction(function () use (&$data) {
                // ✅ 1. Branch bo‘yicha bitta Cashbox topamiz yoki yaratamiz
                $cashbox = \App\Models\Cashbox::firstOrCreate(
                    ['branch_id' => $data['branch_id']],
                    ['name' => 'Avto Cashbox: ' . now()->format('Y-m-d H:i:s')]
                );

                $data['cashbox_id'] = $cashbox->id;

                // ✅ 2. Transaction yozamiz
                \App\Models\CashboxTransaction::create($data);

                // ✅ 3. Cashbox balanceni yangilaymiz (currency_id bo‘yicha)
                $balance = \App\Models\CashboxBalance::firstOrCreate(
                    [
                        'cashbox_id' => $cashbox->id,
                        'currency_id' => $data['currency_id'],
                    ],
                    ['amount' => 0]
                );

                $balance->increment('amount', $data['amount']);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => '❌ Kirim muvaffaqiyatsiz.' . $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => '✅ Kirim muvaffaqiyatli qo‘shildi.']);
    }

    public function storeExpense(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
//            'destination_id' => 'nullable|exists:employees,id',
//            'via_id' => 'required|exists:employees,id',
            'purpose' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:1000',
            'date' => 'nullable|date',
        ]);

        $data['type'] = 'expense';
        $data['date'] = $data['date'] ?? now()->toDateString();
        $data['branch_id'] = auth()->user()->employee->branch_id;

        try {
            DB::transaction(function () use (&$data) {
                // ✅ Branch bo‘yicha bitta Cashbox topamiz yoki yaratamiz
                $cashbox = \App\Models\Cashbox::firstOrCreate(
                    ['branch_id' => $data['branch_id']],
                    ['name' => 'Avto Cashbox: ' . now()->format('Y-m-d H:i:s')]
                );

                $data['cashbox_id'] = $cashbox->id;

                $data['via_id'] = auth()->user()->employee->id;

                // ✅ Balansni tekshiramiz
                $balance = \App\Models\CashboxBalance::firstOrCreate(
                    [
                        'cashbox_id' => $cashbox->id,
                        'currency_id' => $data['currency_id'],
                    ],
                    ['amount' => 0]
                );

                if ($balance->amount < $data['amount']) {
                    throw new \Exception('❌ Kassada yetarli mablag‘ mavjud emas.');
                }

                // ✅ Transaction yozamiz
                \App\Models\CashboxTransaction::create($data);

                // ✅ Balansni kamaytiramiz
                $balance->decrement('amount', $data['amount']);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => '❌ Chiqim muvaffaqiyatsiz.' . $e->getMessage(),
            ], 500);
        }

        return response()->json(['message' => '✅ Chiqim muvaffaqiyatli yozildi.']);
    }

    public function getBalances(): \Illuminate\Http\JsonResponse
    {
        $cashboxes = Cashbox::with(['balances.currency'])
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->get();

        return response()->json([
            'cashboxes' => $cashboxes->map(function ($cashbox) {
                return [
                    'id' => $cashbox->id,
                    'name' => $cashbox->name,
                    'balance' => $cashbox->balances->map(function ($balance) {
                        return [
                            'currency' => $balance->currency->name,
                            'amount' => number_format($balance->amount, 2, '.', ' '),
                        ];
                    })
                ];
            })
        ]);
    }

    public function getTransactions(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = CashboxTransaction::with('currency', 'cashbox')
            ->where('branch_id', auth()->user()->employee->branch_id);

        if ($request->filled('cashbox_id')) {
            $query->where('cashbox_id', $request->cashbox_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        if ($request->filled('search')) {
            $search = mb_strtolower($request->search); // PHP tomonda lowercase qilish
            $query->where(function ($q) use ($search) {
                $q->orWhereHas('via', function ($q3) use ($search) {
                        $q3->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('destination', function ($q4) use ($search) {
                        $q4->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereRaw('LOWER(purpose) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(comment) LIKE ?', ["%{$search}%"]);
            });
        }

        $transactions = $query->orderBy('date', 'desc')->get();

        return response()->json([
            'transactions' => $transactions->map(function ($tx) {
                return [
                    'cashbox' => $tx->cashbox,
                    'type' => $tx->type,
                    'amount' => number_format($tx->amount, 2, '.', ' '),
                    'currency' => $tx->currency,
                    'date' => $tx->date,
                    'source' => $tx->source,
                    'destination' => $tx->destination,
                    'via' => $tx->via,
                    'purpose' => $tx->purpose,
                    'comment' => $tx->comment,
                    'created_at' => $tx->created_at->format('Y-m-d H:i:s'),
                ];
            })
        ]);
    }

    public function transferBetweenCashboxes(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $data = $request->validate([
                'from_currency_id' => 'required|exists:currencies,id',
                'to_currency_id' => 'required|exists:currencies,id|different:from_currency_id',
                'from_amount' => 'required|numeric|min:0.01',
                'to_amount' => 'required|numeric|min:0.01',
                'exchange_rate' => 'required|numeric|min:0.0000001',
                'date' => 'nullable|date',
                'comment' => 'nullable|string|max:1000',
            ]);

            $data['date'] = $data['date'] ?? now()->toDateString();

            $branchId = auth()->user()->employee->branch_id;

            // Har ikkala kassani bir xil filialdan olish
            $cashbox = Cashbox::where('branch_id', $branchId)->firstOrFail();

            // Balansni tekshirish
            $fromBalance = CashboxBalance::where('cashbox_id', $cashbox->id)
                ->where('currency_id', $data['from_currency_id'])
                ->value('amount');

            if ($fromBalance === null || $fromBalance < $data['from_amount']) {
                return response()->json([
                    'message' => '❌ Kassada yetarli mablag‘ mavjud emas.'
                ], 422);
            }

            DB::transaction(function () use ($data, $cashbox) {
                // Chiqim
                CashboxTransaction::create([
                    'cashbox_id' => $cashbox->id,
                    'type' => 'expense',
                    'currency_id' => $data['from_currency_id'],
                    'amount' => $data['from_amount'],
                    'target_cashbox_id' => $cashbox->id,
                    'exchange_rate' => $data['exchange_rate'],
                    'target_amount' => $data['to_amount'],
                    'date' => $data['date'],
                    'comment' => '🔁 Valyuta ayirboshlash',
                    'branch_id' => $cashbox->branch_id,
                    'via_id' => auth()->user()->employee->id, // Hozirgi foydalanuvchi
                    'purpose' => $data['comment'] ?? null,
                ]);

                // Kirim
                CashboxTransaction::create([
                    'cashbox_id' => $cashbox->id,
                    'type' => 'income',
                    'currency_id' => $data['to_currency_id'],
                    'amount' => $data['to_amount'],
                    'target_cashbox_id' => $cashbox->id,
                    'exchange_rate' => $data['exchange_rate'],
                    'target_amount' => $data['from_amount'],
                    'date' => $data['date'],
                    'comment' => '🔁 Valyuta ayirboshlash',
                    'branch_id' => $cashbox->branch_id,
                    'via_id' => auth()->user()->employee->id, // Hozirgi foydalanuvchi
                    'purpose' => $data['comment'] ?? null,
                ]);

                // From balans kamayadi
                CashboxBalance::where('cashbox_id', $cashbox->id)
                    ->where('currency_id', $data['from_currency_id'])
                    ->decrement('amount', $data['from_amount']);

                // To balans oshadi
                CashboxBalance::updateOrCreate(
                    [
                        'cashbox_id' => $cashbox->id,
                        'currency_id' => $data['to_currency_id'],
                    ],
                    [
                        'amount' => DB::raw("amount + {$data['to_amount']}")
                    ]
                );
            });

            return response()->json([
                'message' => "✅ Pul muvaffaqiyatli ayirboshlanib saqlandi:\n{$data['from_amount']} → {$data['to_amount']}"
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => '❌ Xatolik yuz berdi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeRequestForm(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'currency_id' => 'required|exists:currencies,id',
            'amount' => 'required|numeric|min:0.01',
            'purpose' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:1000',
            'deadline' => 'required|date|after_or_equal:today',
        ]);

        try {
            $requestForm = \App\Models\RequestForm::create([
                'employee_id' => $validated['employee_id'],
                'currency_id' => $validated['currency_id'],
                'amount' => $validated['amount'],
                'purpose' => $validated['purpose'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'status' => 'pending',
                'created_by' => auth()->id(),
                'branch_id' => auth()->user()->employee->branch_id,
                'deadline' => $validated['deadline'],
            ]);

            return response()->json([
                'message' => '✅ Talabnoma muvaffaqiyatli yaratildi.',
                'request_id' => $requestForm->id
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => '❌ Talabnoma yaratishda xatolik yuz berdi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRequestForm(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = \App\Models\RequestForm::with('employee', 'currency', 'creator', 'approver')
            ->where('branch_id', auth()->user()->employee->branch_id);

        // 🔍 Search
        if ($request->filled('search')) {
            $search = mb_strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', fn($q2) => $q2->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]))
                    ->orWhereRaw('LOWER(purpose) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(comment) LIKE ?', ["%{$search}%"]);
            });
        }

        // 📅 Deadline sanasi bo‘yicha filter
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('deadline', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->whereDate('deadline', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->whereDate('deadline', '<=', $request->end_date);
        }

        // 📋 Status bo‘yicha filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requestForms = $query->orderBy('deadline', 'desc')->paginate(10);

        return response()->json([
            'data' => $requestForms->getCollection()->transform(function ($form) {
                return [
                    'id' => $form->id,
                    'employee' => $form->employee->name,
                    'currency' => $form->currency->name,
                    'amount' => number_format($form->amount, 2, '.', ' '),
                    'purpose' => $form->purpose,
                    'comment' => $form->comment,
                    'status' => $form->status,
                    'created_at' => $form->created_at->format('Y-m-d H:i:s'),
                    'created_by' => $form->creator->employee->name ?? 'N/A',
                    'approved_by' => $form->approver->employee->name ?? 'N/A',
                    'approved_at' => $form->approved_at ? $form->approved_at->format('Y-m-d H:i:s') : null,
                    'deadline' => $form->deadline,
                ];
            }),
            'current_page' => $requestForms->currentPage(),
            'last_page' => $requestForms->lastPage(),
            'per_page' => $requestForms->perPage(),
            'total' => $requestForms->total(),
            'from' => $requestForms->firstItem(),
            'to' => $requestForms->lastItem(),
        ]);
    }

    public function storeGroupPlan(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'quantity' => 'required|integer|min:0',
        ]);

        $groupPlan = \App\Models\GroupPlan::updateOrCreate(
            [
                'group_id' => $validated['group_id'],
                'month' => $validated['month'],
                'year' => $validated['year'],
            ],
            ['quantity' => $validated['quantity']]
        );

        return response()->json([
            'message' => '✅ Guruh rejasi muvaffaqiyatli saqlandi.',
            'data' => $groupPlan,
        ]);
    }

    public function getGroupPlans(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = \App\Models\GroupPlan::whereHas('group.department.mainDepartment', function ($q) {
            $q->where('branch_id', auth()->user()->employee->branch_id);
        });

        $query->whereHas('group.orders.order', function ($q){
            $q->whereIn('status', ['tailored', 'tailoring']);
        });

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->where('month', $request->month)
                ->where('year', $request->year)
                ->with('group.orders.order.orderModel.submodels.sewingOutPuts', function ($q) use ($request) {
                    $q->whereBetween('created_at', [
                        Carbon::create($request->year, $request->month, 1)->startOfMonth(),
                        Carbon::create($request->year, $request->month, 1)->endOfMonth()
                    ]);
                });
        }

        $query->with('group.orders.order.orderModel.submodels');

        $plans = $query->get();

        return response()->json(GroupPlanResource::collection($plans));
    }

    public function editGroupPlan(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $groupPlan = \App\Models\GroupPlan::findOrFail($id);

        $validated = $request->validate([
            'group_id' => 'sometimes|exists:groups,id',
            'month' => 'sometimes|integer|min:1|max:12',
            'year' => 'sometimes|integer|min:2000|max:2100',
            'quantity' => 'sometimes|integer|min:0',
        ]);

        $groupPlan->update($validated);

        return response()->json([
            'message' => '✅ Guruh rejasi muvaffaqiyatli yangilandi.',
            'data' => $groupPlan,
        ]);
    }

    public function exportGroupsByDepartmentId(Request $request)
    {
        $departmentId = $request->input('department_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $group_id = $request->input('group_id');
        $orderIds = $request->input('order_ids', []);

        if (!$departmentId) {
            return response()->json(['message' => '❌ department_id kiritilmadi.'], 422);
        }

        return Excel::download(
            new DepartmentGroupsExport($departmentId, $startDate, $endDate, $group_id, $orderIds),
            'department_groups.xlsx'
        );
    }

    public function getLatestPurposes(Request $request): \Illuminate\Http\JsonResponse
    {
        $purposes = CashboxTransaction::where('type', $request->type)
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->whereNotNull('purpose')
            ->orderByDesc('created_at')
            ->pluck('purpose')
            ->unique()
            ->take(1000)
            ->values();

        return response()->json($purposes);
    }

    public function getLatestComments(Request $request): \Illuminate\Http\JsonResponse
    {
        $comments = CashboxTransaction::where('type', $request->type)
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->whereNotNull('comment')
            ->orderByDesc('created_at')
            ->pluck('comment')
            ->unique()
            ->take(1000)
            ->values();

        return response()->json($comments);
    }

    public function getLatestSources(Request $request): \Illuminate\Http\JsonResponse
    {
        $sources = CashboxTransaction::where('type', 'income')
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->whereNotNull('source')
            ->orderByDesc('created_at')
            ->pluck('source')
            ->unique()
            ->take(1000)
            ->values();

        return response()->json($sources);
    }
}
