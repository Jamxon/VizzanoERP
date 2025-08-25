<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CeoController extends Controller
{
    public function getGroupResult(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // 1. Barcha kerakli ma'lumotlarni bir vaqtda olish
        $groups = \App\Models\Group::where('department_id', $request->department_id)
            ->with([
                'orders.order.orderModel.submodels' => function ($query) use ($startDate, $endDate) {
                    $query->whereHas('sewingOutputs', function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate]);
                    });
                },
                'orders.order.orderModel.submodels.sewingOutputs' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate])
                        ->select('id', 'submodel_id', 'created_at');
                },
                'orders.order.orderModel.submodels.tarificationCategories.tarifications.tarificationLogs' => function ($query) use ($startDate, $endDate) {
                    $query->whereHas('tarification.tarificationCategory.submodel.sewingOutputs', function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate]);
                    })
                        ->select('id', 'tarification_id', 'employee_id', 'amount_earned');
                },
                'orders.order.orderModel.submodels.tarificationCategories.tarifications.tarificationLogs.employee:id,name,payment_type',
                'orders.order.orderModel.submodels.group.group.employees:id,name,type',
                'responsibleUser.employee:id,name'
            ])
            ->get();

        // 2. Attendance salary'larni alohida query bilan olish
        $groupIds = $groups->pluck('id')->toArray();
        $attendanceSalariesQuery = \DB::table('attendance_salaries as as')
            ->join('employees as e', 'as.employee_id', '=', 'e.id')
            ->join('group_employees as ge', 'e.id', '=', 'ge.employee_id')
            ->whereIn('ge.group_id', $groupIds)
            ->where('e.type', '!=', 'aup')
            ->groupBy('ge.group_id', 'e.id', 'e.name')
            ->select(
                'ge.group_id',
                'e.id as employee_id',
                'e.name as employee_name',
                \DB::raw('SUM(as.amount) as total_salary')
            );

        $results = [];

        foreach ($groups as $group) {
            // 3. Sewing outputs'dan date range'ni topish (optimized)
            $allOutputs = $group->orders->flatMap(function ($orderRelation) {
                return $orderRelation->order->orderModel->submodels->flatMap->sewingOutputs;
            });

            $dates = $allOutputs->pluck('created_at')->map(fn($date) => \Carbon\Carbon::parse($date));
            $firstDate = $dates->isEmpty() ? null : $dates->min();
            $lastDate = $dates->isEmpty() ? null : $dates->max();

            // 4. Tarification'larni hisoblash (optimized)
            $tarificationData = $this->calculateTarificationData($group);

            // 5. Attendance salary'larni hisoblash
            $salaryData = $this->calculateSalaryData($group, $firstDate, $lastDate);

            $results[] = [
                'group' => $group,
                'start_date' => $firstDate?->format('Y-m-d'),
                'end_date' => $lastDate?->format('Y-m-d'),
                'date_range' => $firstDate && $lastDate ? $firstDate->format('Y-m-d') . ' — ' . $lastDate->format('Y-m-d') : null,
                'tarification_total' => $tarificationData['total'],
                'tarification_employees' => $tarificationData['employees'],
                'salary_total' => $salaryData['total'],
                'salary_employees' => $salaryData['employees'],
                'fixed_with_tarification_total' => $tarificationData['fixed_total'],
                'fixed_with_tarification_employees' => $tarificationData['fixed_employees'],
                'responsible_user' => $group->responsibleUser->employee ?? null,
            ];
        }

        return response()->json($results);
    }

    private function calculateTarificationData($group)
    {
        $tarificationTotal = 0;
        $tarificationEmployees = [];
        $fixedWithTarificationTotal = 0;
        $fixedWithTarificationEmployees = [];

        // Collection'larni foydalanib optimized loop
        $allLogs = $group->orders->flatMap(function ($orderRelation) {
            return $orderRelation->order->orderModel->submodels->flatMap(function ($submodel) {
                return $submodel->tarificationCategories->flatMap(function ($category) {
                    return $category->tarifications->flatMap->tarificationLogs;
                });
            });
        });

        // Bir marta loop bilan barcha hisoblarni amalga oshirish
        foreach ($allLogs as $log) {
            $empId = $log->employee_id;
            $amount = $log->amount_earned;

            $tarificationTotal += $amount;

            if (!isset($tarificationEmployees[$empId])) {
                $tarificationEmployees[$empId] = [
                    'employee_id' => $empId,
                    'name' => $log->employee->name ?? 'Nomaʼlum',
                    'salary' => 0
                ];
            }
            $tarificationEmployees[$empId]['salary'] += $amount;

            if ($log->employee && $log->employee->payment_type !== 'piece_work') {
                if (!isset($fixedWithTarificationEmployees[$empId])) {
                    $fixedWithTarificationEmployees[$empId] = [
                        'employee_id' => $empId,
                        'name' => $log->employee->name ?? 'Nomaʼlum',
                        'tarification_salary' => 0
                    ];
                }
                $fixedWithTarificationEmployees[$empId]['tarification_salary'] += $amount;
                $fixedWithTarificationTotal += $amount;
            }
        }

        return [
            'total' => $tarificationTotal,
            'employees' => array_values($tarificationEmployees),
            'fixed_total' => $fixedWithTarificationTotal,
            'fixed_employees' => array_values($fixedWithTarificationEmployees),
        ];
    }

    private function calculateSalaryData($group, $firstDate, $lastDate)
    {
        if (!$firstDate || !$lastDate) {
            return ['total' => 0, 'employees' => []];
        }

        $salaryTotal = 0;
        $salaryEmployees = [];

        // Raw SQL query bilan optimized hisob
        $salaryResults = \DB::table('attendance_salaries as as')
            ->join('employees as e', 'as.employee_id', '=', 'e.id')
            ->join('group_employees as ge', 'e.id', '=', 'ge.employee_id')
            ->where('ge.group_id', $group->id)
            ->where('e.type', '!=', 'aup')
            ->whereBetween('as.date', [$firstDate->format('Y-m-d'), $lastDate->format('Y-m-d')])
            ->groupBy('e.id', 'e.name')
            ->select('e.id as employee_id', 'e.name', \DB::raw('SUM(as.amount) as total_salary'))
            ->get();

        foreach ($salaryResults as $result) {
            if ($result->total_salary > 0) {
                $salaryEmployees[] = [
                    'employee_id' => $result->employee_id,
                    'name' => $result->name,
                    'salary' => $result->total_salary
                ];
                $salaryTotal += $result->total_salary;
            }
        }

        return [
            'total' => $salaryTotal,
            'employees' => $salaryEmployees,
        ];
    }

}