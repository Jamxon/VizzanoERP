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
            ],
            'orderPrintingTimes' => [
                "id" => $this->orderPrintingTime->id,
                "planned_time" => $this->orderPrintingTime->planned_time,
                "actual_time" => $this->orderPrintingTime->actual_time,
                "status" => $this->orderPrintingTime->status,
                "comment" => $this->orderPrintingTime->comment,
                "user" => $this->orderPrintingTime->user,
            ],
        ];
    }

}