<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CeoController extends Controller
{
    public function getGroupResult(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $departmentId = $request->department_id;

        // 1. Optimized main query with selective loading
        $groups = \App\Models\Group::where('department_id', $departmentId)
            ->with([
                'responsibleUser.employee:id,name',
                'orders' => function ($query) use ($startDate, $endDate) {
                    $query->whereHas('order.orderModel.submodels.sewingOutputs', function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate]);
                    })
                        ->with([
                            'order:id,name,quantity,status,start_date,end_date',
                            'order.orderModel:id,order_id,model_id,rasxod,status,minute',
                            'order.orderModel.model:id,name',
                            'order.orderModel.submodels:id,order_model_id,group_id'
                        ]);
                }
            ])
            ->get(['id', 'name', 'department_id']);

        // 2. Batch load sewing outputs with date filtering
        $submodelIds = [];
        foreach ($groups as $group) {
            foreach ($group->orders as $order) {
                foreach ($order->order->orderModel->submodels as $submodel) {
                    $submodelIds[] = $submodel->id;
                }
            }
        }

        // 3. Single query for all sewing outputs
        $sewingOutputs = \App\Models\SewingOutput::whereIn('order_submodel_id', $submodelIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('id', 'order_submodel_id', 'quantity', 'created_at')
            ->get()
            ->groupBy('order_submodel_id');

        // 4. Single query for all tarification data
        $tarificationLogs = \App\Models\TarificationLog::whereHas('tarification.tarificationCategory.submodel', function ($q) use ($submodelIds) {
            $q->whereIn('id', $submodelIds);
        })
            ->with('employee:id,name,type,payment_type')
            ->select('id', 'tarification_id', 'employee_id', 'amount_earned', 'created_at')
            ->get();

        // Group tarification logs by submodel
        $tarificationBySubmodel = [];
        foreach ($tarificationLogs as $log) {
            $submodelId = $log->tarification->tarificationCategory->submodel_id;
            $tarificationBySubmodel[$submodelId][] = $log;
        }

        // 5. Calculate date range from sewing outputs
        $allDates = $sewingOutputs->flatten()->pluck('created_at')->map(function ($date) {
            return \Carbon\Carbon::parse($date);
        });

        $firstDate = $allDates->min();
        $lastDate = $allDates->max();

        // 6. Get unique employee IDs and batch load their salary data
        $employeeIds = collect($tarificationLogs)->pluck('employee_id')->unique()->values();

        $salaryData = [];
        if ($firstDate && $lastDate && $employeeIds->isNotEmpty()) {
            $salaryData = \App\Models\AttendanceSalary::whereIn('employee_id', $employeeIds)
                ->whereBetween('date', [$firstDate->format('Y-m-d'), $lastDate->format('Y-m-d')])
                ->selectRaw('employee_id, SUM(amount) as total_amount')
                ->groupBy('employee_id')
                ->pluck('total_amount', 'employee_id')
                ->toArray();
        }

        // 7. Process data efficiently
        $tarificationTotal = 0;
        $tarificationEmployees = [];
        $fixedWithTarificationTotal = 0;
        $fixedWithTarificationEmployees = [];
        $salaryTotal = 0;
        $salaryEmployees = [];

        // Attach sewing outputs to submodels
        foreach ($groups as $group) {
            foreach ($group->orders as $order) {
                foreach ($order->order->orderModel->submodels as $submodel) {
                    $submodel->sewingOutputs = $sewingOutputs->get($submodel->id, collect());
                    $submodel->total_quantity = $submodel->sewingOutputs->sum('quantity');

                    if ($submodel->sewingOutputs->isNotEmpty()) {
                        $submodel->min_date = $submodel->sewingOutputs->min('created_at');
                        $submodel->max_date = $submodel->sewingOutputs->max('created_at');
                    }

                    // Process tarification data for this submodel
                    $logs = $tarificationBySubmodel[$submodel->id] ?? [];
                    foreach ($logs as $log) {
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

                        // Handle fixed payment type employees
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
        }

        // Process salary data for non-AUP employees
        foreach ($salaryData as $employeeId => $salarySum) {
            // Get employee info from tarification logs
            $employee = collect($tarificationLogs)->where('employee_id', $employeeId)->first()?->employee;

            if ($employee && $employee->type !== 'aup' && $salarySum > 0) {
                $salaryEmployees[] = [
                    'employee_id' => $employeeId,
                    'name' => $employee->name,
                    'salary' => $salarySum
                ];
                $salaryTotal += $salarySum;
            }
        }

        return response()->json([
            'groups' => $groups,
            'first_date' => $firstDate ? $firstDate->format('Y-m-d') : null,
            'last_date' => $lastDate ? $lastDate->format('Y-m-d') : null,
            'tarification_total' => $tarificationTotal,
            'tarification_employees' => array_values($tarificationEmployees),
            'salary_total' => $salaryTotal,
            'salary_employees' => $salaryEmployees,
            'fixed_with_tarification_total' => $fixedWithTarificationTotal,
            'fixed_with_tarification_employees' => array_values($fixedWithTarificationEmployees),
        ]);
    }


}