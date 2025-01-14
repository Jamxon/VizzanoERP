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
                        "model" => $orderModel->model->makeHidden(['submodels']),
                        "submodels" => $orderModel->submodels
                            ->groupBy('submodel_id')
                            ->map(function ($groupedSubmodels) {
                                $firstSubmodel = $groupedSubmodels->first();
                                return [
                                    "id" => $firstSubmodel->id,
                                    "submodel" => $firstSubmodel->submodel->makeHidden(['sizes', 'modelColors']),
                                    "sizes" => $groupedSubmodels->pluck('size')->unique()->values(),
                                    "modelColors" => $groupedSubmodels->pluck('modelColor')->unique()->values(),
                                    "total_quantity" => $groupedSubmodels->sum('quantity'),
                                ];
                            })->values(),
                    ];
                });
            }),
        ];
    }


}
