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
            "instructions" => $this->instructions->map(function ($instruction) {
                return [
                    "id" => $instruction->id,
                    "title" => $instruction->title,
                    "description" => $instruction->description,
                ];
            }),
            "comment" => $this->comment,
            "order_printing_time" => $this->orderPrintingTime ?  [
                "id" => $this->orderPrintingTime->id,
                "planned_time" => $this->orderPrintingTime->planned_time,
                "actual_time" => $this->orderPrintingTime->actual_time,
                "status" => $this->orderPrintingTime->status,
                "comment" => $this->orderPrintingTime->comment,
                "user" => $this->orderPrintingTime->user,
                "model" => $this->orderModel->model->makeHidden(['submodels']),
                "submodels" => $this->orderModel->submodels
                    ->groupBy('submodel_id')
                    ->map(function ($groupedSubmodels) {
                        $firstSubmodel = $groupedSubmodels->first();
                        return [
                            "id" => $firstSubmodel->id,
                            "submodel" => $firstSubmodel->submodel->makeHidden(['sizes', 'modelColors']),
                        ];
                    })->values(),
                'sizes' => $this->orderModel->sizes->map(function ($size) {
                    return [
                        'id' => $size->id,
                        'size' => $size->size,
                        'quantity' => $size->quantity,
                    ];
                }),
            ] : null,
        ];
    }

}