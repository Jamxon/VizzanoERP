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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'responsibleUser' => [
                'id' => $this->responsibleUser->employee->id,
                'name' => $this->responsibleUser->employee->name,
            ],
            'orders' => $this->orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'model' => [
                        'id' => $order->orderSubmodel->orderModel->model->id,
                        'name' => $order->orderSubmodel->orderModel->model->name,
                    ],
                    'submodel' => [
                        'id' => $order->orderSubmodel->submodel->id,
                        'name' => $order->orderSubmodel->submodel->name,
                    ],
                    'sewingOutputs' => $order->orderSubmodel->sewingOutputs
                        ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
                        ->map(function ($sewingOutput) {
                            return [
                                'id' => $sewingOutput->id,
                                'quantity' => $sewingOutput->quantity,
                                'time' => [
                                    'id' => $sewingOutput->time->id,
                                    'time' => $sewingOutput->time->time,
                                ],
                            ];
                        }),
                    'totalQuantity' => $order->orderSubmodel->sewingOutputs
                        ->sum('quantity'),
                ];
            }),
        ];
    }
}
