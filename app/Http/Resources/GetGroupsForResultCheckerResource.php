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
        $workTimeInSeconds = 500 * 60; // 500 daqiqa

        return [
            'id' => $this->id,
            'name' => $this->name,
            'responsibleUser' => [
                'id' => $this->responsibleUser->employee->id ?? null,
                'name' => $this->responsibleUser->employee->name ?? null,
            ],
            'orders' => $this->orders->map(function ($order) use ($today, $workTimeInSeconds) {
                $submodel = $order->orderSubmodel;
                $orderModel = $submodel->orderModel ?? null;
                $rasxod = $orderModel->rasxod ?? 0;
                $spend = ($rasxod / 250) * 60; // har bir dona uchun sekund

                // Guruh ID ni olish
                $groupId = $order->group_id ?? $order->employee?->group_id;

                // Xodimlar soni (bugun kelganlar)
                $employeeCount = \App\Models\Attendance::whereDate('date', $today)
                    ->where('status', 'present')
                    ->whereHas('employee', function ($q) use ($groupId) {
                        $q->where('group_id', $groupId);
                    })
                    ->distinct('employee_id')
                    ->count('employee_id');

                // Bugungi sewing outputs
                $todaySewingOutputs = $submodel->sewingOutputs
                    ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
                    ->values();

                $totalQuantity = $todaySewingOutputs->sum('quantity');

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
                        'id' => $submodel->submodel->id ?? null,
                        'name' => $submodel->submodel->name ?? null,
                    ],
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
                    'totalQuantity' => $totalQuantity,
                ];
            }),
        ];
    }

}
