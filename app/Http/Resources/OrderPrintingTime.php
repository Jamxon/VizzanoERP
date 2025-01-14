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
            "order_printing_times" => $this->orderModels->flatMap(function ($orderModel) {
                return $orderModel->orderPrintingTimes->map(function ($orderPrintingTime) use ($orderModel) {
                    return [
                        "id" => $orderPrintingTime->id,
                        "planned_time" => $orderPrintingTime->planned_time,
                        "actual_time" => $orderPrintingTime->actual_time,
                        "status" => $orderPrintingTime->status,
                        "comment" => $orderPrintingTime->comment,
                        "user" => $orderPrintingTime->user,
                        "order_model" => [
                            "id" => $orderModel->id,
                            "model" => $orderModel->model->makeHidden(['submodels']),
                            "submodels" => $orderModel->submodels->map(function ($submodel) {
                                return [
                                    "id" => $submodel->id,
                                    "submodel" => $submodel->submodel->makeHidden(['sizes', 'modelColors']),
                                    "size" => $submodel->size,
                                    "modelColor" => $submodel->modelColor,
                                ];
                            }),
                        ],
                    ];
                });
            }),
        ];
    }

}
