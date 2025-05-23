<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GetOrderGroupMasterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->order->id,
            'name' => $this->order->name,
            'quantity' => $this->order->quantity,
            'start_date' => $this->order->start_date,
            'end_date' => $this->order->end_date,
            'rasxod' => $this->order->rasxod,
            'status' => $this->order->status,
            'comment' => $this->order->comment,
            'remainAmount' => $this->order->total_sewn_quantity ?? 0,
            'orderModel' => [
                'id' => $this->order->orderModel->id,
                'model' => [
                    'id' => $this->order->orderModel->model->id,
                    'name' => $this->order->orderModel->model->name,
                ],
                'material' => [
                    'id' => $this->order->orderModel->material->id ?? 0,
                    'name' => $this->order->orderModel->material->name ?? null,
                ],
                'sizes' => $this->order->orderModel->sizes->map(function ($size) {
                    return [
                        'id' => $size->id,
                        'size' => $size->size,
                        'quantity' => $size->quantity,
                    ];
                }),
                'submodels' => $this->order->orderModel->submodels->map(function ($submodel) {
                    return [
                        'id' => $submodel->id,
                        'submodel' => $submodel->submodel,
                        'tarificationCategories' => $submodel->tarificationCategories->map(function ($category) {
                            return [
                                'id' => $category->id,
                                'name' => $category->name,
                                'tarifications' => $category->tarifications,
                            ];
                        }),
                        'sewingOutputs' => $submodel->sewingOutputs
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
                    ];
                }),
            ],
            'instructions' => $this->order->instructions,
        ];
    }
}
