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
            "order_printing_times" => $this->orderModels->map(function ($orderModel) {
                return [
//                    "id" => $orderModel->orderPrintingTimes->id,
//                    "planned_time" => $orderModel->orderPrintingTimes->planned_time,
//                    "actual_time" => $orderModel->orderPrintingTimes->actual_time,
//                    "status" => $orderModel->orderPrintingTimes->status,
                    "comment" => $orderModel->orderPrintingTimes->comment,
//                    "user" => $orderModel->orderPrintingTimes->user,
                    "model" => $orderModel->model->makeHidden(['submodels']),
                    "submodels" => $orderModel->submodels->map(function ($submodel) {
                        return [
                            "id" => $submodel->id,
                            "submodel" => $submodel->submodel->makeHidden(['sizes', 'modelColors']),
                            "size" => $submodel->size,
                            "modelColor" => $submodel->modelColor
                        ];
                    }),
                ];
            })
        ];
    }
}
