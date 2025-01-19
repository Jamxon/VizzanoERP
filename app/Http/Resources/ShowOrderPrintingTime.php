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
            "order_printing_times" => $this->orderModel ? $this->orderModel->orderPrintingTimes ? [
                "id" => $this->orderModel->orderPrintingTimes->id,
                "planned_time" => $this->orderModel->orderPrintingTimes->planned_time,
                "actual_time" => $this->orderModel->orderPrintingTimes->actual_time,
                "status" => $this->orderModel->orderPrintingTimes->status,
                "comment" => $this->orderModel->orderPrintingTimes->comment,
                "user" => $this->orderModel->orderPrintingTimes->user,
                "model" => $this->orderModel->model->makeHidden(['submodels']),
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
                'sizes' => $this->orderModel->sizes->map(function ($size) {
                    return [
                        'id' => $size->id,
                        'size' => $size->size,
                        'quantity' => $size->quantity,
                    ];
                }),
            ] : [] : [],
        ];
    }
}
