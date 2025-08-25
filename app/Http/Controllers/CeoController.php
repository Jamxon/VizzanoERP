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
            ->with([ 'orders' => function ($query) use ($startDate, $endDate)
            {
                // orders faqat sana bo‘yicha filter qilinadi
                $query->whereHas('order.orderModel.submodels.sewingOutputs', function ($q) use ($startDate, $endDate)
                {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                    ->with([ 'order' => function ($q) {
                        $q->select('id', 'name', 'quantity', 'status', 'start_date', 'end_date'); },
                        'order.orderModel' => function ($q) { $q->select('id', 'order_id', 'model_id', 'rasxod', 'status', 'minute')
                            ->with('model:id,name'); },
                        'order.orderModel.submodels' => function ($q) { $q->select('id', 'order_model_id')
                            ->with(['sewingOutputs' => function ($sq) {
                                $sq->select( 'id', 'order_submodel_id', 'quantity', 'created_at' );
                            }])
                            ->withSum('sewingOutputs as total_quantity', 'quantity')
                            ->withMin('sewingOutputs as min_date', 'created_at')
                            ->withMax('sewingOutputs as max_date', 'created_at'); } ]); },
                'responsibleUser.employee',
                'orders.order.orderModel.submodels.tarificationCategories.tarifications.tarificationLogs.employee'
            ])
            ->get();

        $result = [];
        $firstDate = null;
        $lastDate = null;

        foreach ($groups as $group) {
            $tarificationTotal = 0;
            $tarificationEmployees = [];
            $fixedWithTarificationTotal = 0;
            $fixedWithTarificationEmployees = [];


            foreach ($group->orders as $order) {
                $salaryTotal = 0;
                $salaryEmployees = [];
                foreach ($submodel->sewingOutputs as $output) {
                    $createdAt = \Carbon\Carbon::parse($output->created_at);

                    if (is_null($firstDate) || $createdAt->lt($firstDate)) {
                        $firstDate = $createdAt;
                    }
                    if (is_null($lastDate) || $createdAt->gt($lastDate)) {
                        $lastDate = $createdAt;
                    }
                }
                foreach ($order->order->orderModel->submodels as $submodel) {
                    $group = $submodel->group->group ?? null;
                    if (!$group) continue;

                    foreach ($group->employees as $employee) {
                        // faqat AUP bo‘lmagan xodimlarni olamiz
                        if ($employee->type === 'aup') {
                            continue;
                        }

                        $salarySum = $employee->attendanceSalaries()
                            ->whereBetween('date', [$firstDate->format('Y-m-d'), $lastDate->format('Y-m-d')])
                            ->sum('amount');

                        if ($salarySum > 0) {
                            $salaryEmployees[] = [
                                'employee_id' => $employee->id,
                                'name' => $employee->name,
                                'salary' => $salarySum
                            ];
                            $salaryTotal += $salarySum;
                        }
                    }
                    foreach ($submodel->tarificationCategories as $tarificationCategory) {
                        foreach ($tarificationCategory->tarifications as $tarification) {
                            foreach ($tarification->tarificationLogs as $tarificationLog) {
                                $tarificationTotal += $tarificationLog->amount_earned;
                                $empId = $tarificationLog->employee_id;

                                if (!isset($tarificationEmployees[$empId])) {
                                    $tarificationEmployees[$empId] = [
                                        'employee_id' => $empId,
                                        'name' => $tarificationLog->employee->name ?? 'Nomaʼlum',
                                        'salary' => 0
                                    ];
                                }
                                $tarificationEmployees[$empId]['salary'] += $tarificationLog->amount_earned;

                                if ($tarificationLog->employee && $tarificationLog->employee->payment_type !== 'piece_work') {
                                    if (!isset($fixedWithTarificationEmployees[$empId])) {
                                        $fixedWithTarificationEmployees[$empId] = [
                                            'employee_id' => $empId,
                                            'name' => $tarificationLog->employee->name ?? 'Nomaʼlum',
                                            'tarification_salary' => 0
                                        ];
                                    }
                                    $fixedWithTarificationEmployees[$empId]['tarification_salary'] += $tarificationLog->amount_earned;
                                    $fixedWithTarificationTotal += $tarificationLog->amount_earned;
                                }
                            }
                        }
                    }
                }
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