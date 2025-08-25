<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CeoController extends Controller
{
    public function getGroupResult(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $groups = \App\Models\Group::where('department_id', $request->department_id)
            ->with([
                'orders' => function ($query) use ($startDate, $endDate) {
                    $query->whereHas('order.orderModel.submodels.sewingOutputs', function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate]);
                    })
                        ->with([
                            'order.orderModel.submodels.sewingOutputs',
                            'order.orderModel.submodels.tarificationCategories.tarifications.tarificationLogs.employee',
                            'order.orderModel.submodels.group.group.employees.attendanceSalaries',
                        ]);
                },
                'responsibleUser.employee'
            ])
            ->get();

        $results = [];

        foreach ($groups as $group) {
            $firstDate = null;
            $lastDate = null;

            $tarificationTotal = 0;
            $tarificationEmployees = [];
            $fixedWithTarificationTotal = 0;
            $fixedWithTarificationEmployees = [];
            $salaryTotal = 0;
            $salaryEmployees = [];

            foreach ($group->orders as $orderRelation) {
                $order = $orderRelation->order;

                foreach ($order->orderModel->submodels as $submodel) {
                    // sewingOutputs dan birinchi va oxirgi sana
                    foreach ($submodel->sewingOutputs as $output) {
                        $createdAt = \Carbon\Carbon::parse($output->created_at);
                        if (is_null($firstDate) || $createdAt->lt($firstDate)) {
                            $firstDate = $createdAt;
                        }
                        if (is_null($lastDate) || $createdAt->gt($lastDate)) {
                            $lastDate = $createdAt;
                        }
                    }

                    // tarifikatsiya hisoblash
                    foreach ($submodel->tarificationCategories as $category) {
                        foreach ($category->tarifications as $tarification) {
                            foreach ($tarification->tarificationLogs as $log) {
                                $tarificationTotal += $log->amount_earned;
                                $empId = $log->employee_id;

                                if (!isset($tarificationEmployees[$empId])) {
                                    $tarificationEmployees[$empId] = [
                                        'employee_id' => $empId,
                                        'name' => $log->employee->name ?? 'Nomaʼlum',
                                        'salary' => 0
                                    ];
                                }
                                $tarificationEmployees[$empId]['salary'] += $log->amount_earned;

                                if ($log->employee && $log->employee->payment_type !== 'piece_work') {
                                    if (!isset($fixedWithTarificationEmployees[$empId])) {
                                        $fixedWithTarificationEmployees[$empId] = [
                                            'employee_id' => $empId,
                                            'name' => $log->employee->name ?? 'Nomaʼlum',
                                            'tarification_salary' => 0
                                        ];
                                    }
                                    $fixedWithTarificationEmployees[$empId]['tarification_salary'] += $log->amount_earned;
                                    $fixedWithTarificationTotal += $log->amount_earned;
                                }
                            }
                        }
                    }

                    // attendance bo‘yicha hisob
                    $groupModel = $submodel->group->group ?? null;
                    if ($groupModel) {
                        foreach ($groupModel->employees as $employee) {
                            if ($employee->type === 'aup') {
                                continue;
                            }
                            $salarySum = $employee->attendanceSalaries()
                                ->whereBetween('date', [$firstDate?->format('Y-m-d'), $lastDate?->format('Y-m-d')])
                                ->sum('amount');

                            if ($salarySum > 0) {
                                if (!isset($salaryEmployees[$employee->id])) {
                                    $salaryEmployees[$employee->id] = [
                                        'employee_id' => $employee->id,
                                        'name' => $employee->name,
                                        'salary' => 0
                                    ];
                                }
                                $salaryEmployees[$employee->id]['salary'] += $salarySum;
                                $salaryTotal += $salarySum;
                            }
                        }
                    }
                }
            }

            $results[] = [
                'group' => $group,
                'start_date' => $firstDate?->format('Y-m-d'),
                'end_date' => $lastDate?->format('Y-m-d'),
                'date_range' => $firstDate && $lastDate ? $firstDate->format('Y-m-d') . ' — ' . $lastDate->format('Y-m-d') : null,

                'tarification_total' => $tarificationTotal,
                'tarification_employees' => array_values($tarificationEmployees),

                'salary_total' => $salaryTotal,
                'salary_employees' => array_values($salaryEmployees),

                'fixed_with_tarification_total' => $fixedWithTarificationTotal,
                'fixed_with_tarification_employees' => array_values($fixedWithTarificationEmployees),
                'responsible_user' => $group->responsibleUser->employee ?? null,
            ];
        }

        return response()->json($results);
    }

}