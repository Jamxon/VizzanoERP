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
            "comment" => $this->comment ?? null,
            'orderModel' => [
                'id' => $this->orderModel->id ?? null,
                'model' => [
                    'id' => $this->orderModel->model->id ?? null,
                    'name' => $this->orderModel->model->name ?? null,
                ],
                'material' => [
                    'id' => $this->orderModel->material->id ?? 0,
                    'name' => $this->orderModel->material->name ?? null,
                ],
            ],
            'orderPrintingTimes' => [
                "id" => $this->orderPrintingTime->id ?? null,
                "planned_time" => $this->orderPrintingTime->planned_time ?? null,
                "actual_time" => $this->orderPrintingTime->actual_time ?? null,
                "status" => $this->orderPrintingTime->status ?? null,
                "comment" => $this->orderPrintingTime->comment ?? null,
                "user" => $this->orderPrintingTime->user ?? null,
            ],
        ];
    }

}