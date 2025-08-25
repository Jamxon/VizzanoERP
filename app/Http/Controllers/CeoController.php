<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CeoController extends Controller
{
    public function getGroupResult(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // 1. Guruhlarni yuklash (faqat kerakli narsalar bilan)
        $groups = \App\Models\Group::where('department_id', $request->department_id)
            ->with([
                'orders.order:id,name,quantity,status,start_date,end_date',
                'orders.order.orderModel:id,order_id,model_id,rasxod,status,minute',
                'orders.order.orderModel.model:id,name',
                'orders.order.orderModel.submodels' => function ($q) {
                    $q->select('id', 'order_model_id')
                        ->withSum('sewingOutputs as total_quantity', 'quantity')
                        ->withMin('sewingOutputs as min_date', 'created_at')
                        ->withMax('sewingOutputs as max_date', 'created_at');
                },
                'orders.order.orderModel.submodels.tarificationCategories.tarifications.tarificationLogs.employee:id,name,payment_type',
            ])
            ->get();

        $tarificationTotal = 0;
        $tarificationEmployees = [];
        $fixedWithTarificationTotal = 0;
        $fixedWithTarificationEmployees = [];

        $firstDate = null;
        $lastDate = null;

        // 2. Tarification hisoblash
        foreach ($groups as $group) {
            foreach ($group->orders as $order) {
                foreach ($order->order->orderModel->submodels as $submodel) {
                    // sewing outputs dan min/max olish
                    if ($submodel->min_date && ($firstDate === null || $submodel->min_date < $firstDate)) {
                        $firstDate = $submodel->min_date;
                    }
                    if ($submodel->max_date && ($lastDate === null || $submodel->max_date > $lastDate)) {
                        $lastDate = $submodel->max_date;
                    }

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
                }
            }
        }

        // 3. Attendance salaries (bitta query bilan olish)
        $salaryEmployees = [];
        $salaryTotal = 0;

        if ($firstDate && $lastDate) {
            $salaries = \App\Models\AttendanceSalary::whereBetween('date', [
                \Carbon\Carbon::parse($firstDate)->format('Y-m-d'),
                \Carbon\Carbon::parse($lastDate)->format('Y-m-d'),
            ])
                ->whereHas('employee', function ($q) {
                    $q->where('type', '!=', 'aup'); // faqat AUP bo‘lmaganlar
                })
                ->with('employee:id,name,type')
                ->selectRaw('employee_id, SUM(amount) as total_salary')
                ->groupBy('employee_id')
                ->get();

            foreach ($salaries as $s) {
                $salaryEmployees[] = [
                    'employee_id' => $s->employee_id,
                    'name' => $s->employee->name,
                    'salary' => $s->total_salary
                ];
                $salaryTotal += $s->total_salary;
            }
        }

        return response()->json([
            'groups' => $groups,
            'first_date' => $firstDate ? date('Y-m-d', strtotime($firstDate)) : null,
            'last_date' => $lastDate ? date('Y-m-d', strtotime($lastDate)) : null,
            'tarification_total' => $tarificationTotal,
            'tarification_employees' => array_values($tarificationEmployees),

            'salary_total' => $salaryTotal,
            'salary_employees' => $salaryEmployees,

            'fixed_with_tarification_total' => $fixedWithTarificationTotal,
            'fixed_with_tarification_employees' => array_values($fixedWithTarificationEmployees),
        ]);
    }


}