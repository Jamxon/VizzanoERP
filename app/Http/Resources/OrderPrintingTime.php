<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderPrintingTime extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "quantity" => $this->quantity,
            "status" => $this->status,
            "start_date" => $this->start_date,
            "end_date" => $this->end_date,
            "comment" => $this->comment,
            'orderModel' => [
                'id' => $this->orderModel->id,
                'model' => [
                    'id' => $this->orderModel->model->id,
                    'name' => $this->orderModel->model->name,
                ],
                'material' => [
                    'id' => $this->orderModel->material->id ?? 0,
                    'name' => $this->orderModel->material->name ?? null,
                ],
                'sizes' => $this->orderModel->sizes->map(function ($size) {
                    return [
                        'id' => $size->id,
                        'size' => $size->size,
                        'quantity' => $size->quantity,
                    ];
                }),
                'submodels' => $this->orderModel->submodels->map(function ($submodel) {
                    return [
                        'id' => $submodel->id,
                        'submodel' => $submodel->submodel,
                    ];
                }),
            ],
        ];
    }

}