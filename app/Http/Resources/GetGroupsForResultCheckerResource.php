<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GetGroupsForResultCheckerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = now()->toDateString();

        // 1. Guruhlar ro'yxati
        $groupIds = $this->orders->pluck('group_id')->filter()->unique();

        // 2. Har bir group uchun work time ni hisoblab olish
        $workTimeByGroup = \App\Models\Group::whereIn('groups.id', $groupIds)
            ->join('departments', 'groups.department_id', '=', 'departments.id')
            ->selectRaw("
            groups.id as group_id,
            EXTRACT(EPOCH FROM (departments.end_time - departments.start_time - (departments.break_time * INTERVAL '1 second'))) as work_seconds
        ")
            ->pluck('work_seconds', 'group_id'); // [group_id => work_seconds]

        return [
            'id' => $this->id,
            'name' => $this->name,
            'responsibleUser' => [
                'id' => $this->responsibleUser->employee->id ?? null,
                'name' => $this->responsibleUser->employee->name ?? null,
            ],
            'orders' => $this->orders
                ->filter(function ($order) {
                    return in_array(optional($order->order)->status, ['tailoring', 'pending', 'cutting']);
                })
                ->map(function ($order) use ($today, $workTimeByGroup) {
                    $submodel = $order->orderSubmodel;
                    $orderModel = $submodel->orderModel ?? null;
                    $rasxod = $orderModel->rasxod ?? 0;
                    $spend = ($rasxod / 250) * 60;

                    $groupId = $order->group_id;
                    $workTimeInSeconds = $workTimeByGroup[$groupId] ?? (500 * 60);

                    $employeeCount = \App\Models\Attendance::whereDate($today)
                        ->where('status', 'present')
                        ->whereHas('employee', function ($q) use ($groupId) {
                            $q->where('group_id', $groupId);
                        })
                        ->distinct('employee_id')
                        ->count('employee_id');

                    $totalQuantity = $submodel->sewingOutputs->sum('quantity');

                    $todaySewingOutputs = $submodel->sewingOutputs
                        ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
                        ->values();

                    $todayTotalQuantity = $todaySewingOutputs->sum('quantity');

                    $todayPlan = ($spend > 0 && $employeeCount > 0)
                        ? intval(($workTimeInSeconds * $employeeCount) / $spend)
                        : 0;

                    return [
                        'id' => $order->id,
                        'model' => [
                            'id' => $orderModel->model->id ?? null,
                            'name' => $orderModel->model->name ?? null,
                        ],
                        'submodel' => [
                            'id' => $submodel->id ?? null,
                            'name' => $submodel->submodel->name ?? null,
                        ],
                        'status' => $order->order->status ?? null,
                        'todayPlan' => $todayPlan,
                        'sewingOutputs' => $todaySewingOutputs->map(function ($sewingOutput) {
                            return [
                                'id' => $sewingOutput->id,
                                'quantity' => $sewingOutput->quantity,
                                'time' => [
                                    'id' => $sewingOutput->time->id ?? null,
                                    'time' => $sewingOutput->time->time ?? null,
                                ],
                            ];
                        }),
                        'todayTotalQuantity' => $todayTotalQuantity,
                        'totalQuantity' => $totalQuantity,
                    ];
                })->values(), // values() qo‘shiladi
        ];
    }

}
