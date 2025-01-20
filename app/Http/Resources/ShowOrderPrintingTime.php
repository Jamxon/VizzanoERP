<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShowOrderPrintingTime extends JsonResource
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
            "instructions" => $this->instructions->map(function ($instruction) {
                return [
                    "id" => $instruction->id,
                    "title" => $instruction->title,
                    "description" => $instruction->description,
                ];
            }),
            "order_model" => $this->orderModel ? [
                "id" => $this->orderModel->id,
                "model" => $this->orderModel->model->makeHidden(['submodels']),
                "sizes" => $this->orderModel->sizes->map(function ($size) {
                    return [
                        "id" => $size->id,
                        "size" => $size->size,
                        "quantity" => $size->quantity,
                    ];
                }),
                "submodels" => $this->orderModel->submodels
                    ->groupBy('submodel_id')
                    ->map(function ($groupedSubmodels) {
                        $firstSubmodel = $groupedSubmodels->first();
                        return [
                            "id" => $firstSubmodel->id,
                            "submodel" => $firstSubmodel->submodel->makeHidden(['sizes', 'modelColors']),
                            "total_quantity" => $groupedSubmodels->sum('quantity'),
                        ];
                    })->values(),
                "order_printing_times" => $this->orderModel->orderPrintingTimes->map(function ($orderPrintingTime) {
                    return [
                        "id" => $orderPrintingTime->id,
                        "planned_time" => $orderPrintingTime->planned_time,
                        "actual_time" => $orderPrintingTime->actual_time,
                        "status" => $orderPrintingTime->status,
                        "comment" => $orderPrintingTime->comment,
                        "user" => $orderPrintingTime->user,
                    ];
                }),
            ] : [],
        ];
    }

}
