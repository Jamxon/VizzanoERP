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
            'orderModel' => [
                'id' => $this->order->orderModel->id,
                'name' => $this->order->orderModel->name,
                'model' => [
                    'id' => $this->order->orderModel->model->id,
                    'name' => $this->order->orderModel->model->name,
                ],
                'material' => [
                    'id' => $this->order->orderModel->material->id,
                    'name' => $this->order->orderModel->material->name,
                ],
                'sizes' => $this->order->orderModel->sizes->map(function ($size) {
                    return [
                        'id' => $size->size->id,
                        'name' => $size->size->name,
                    ];
                }),
                'submodels' => $this->order->orderModel->submodels->map(function ($submodel) {
                    return [
                        'id' => $submodel->submodel->id,
                        'name' => $submodel->submodel->name,
                    ];
                }),
            ],
        ];
    }
}
