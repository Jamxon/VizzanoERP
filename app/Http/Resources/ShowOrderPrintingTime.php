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
            "instructions" => $this->instructions->map(function ($instruction) {
                return [
                    "id" => $instruction->id,
                    "title" => $instruction->title,
                    "description" => $instruction->description,
                ];
            }) ?? null,
            "comment" => $this->comment,
            "order_printing_times" => $this->orderPrintingTime->map(function ($orderPrintingTime) {
                return [
                    "id" => $orderPrintingTime->id,
                    "planned_time" => $orderPrintingTime->planned_time,
                    "actual_time" => $orderPrintingTime->actual_time,
                    "status" => $orderPrintingTime->status,
                    "comment" => $orderPrintingTime->comment,
                    "user" => $orderPrintingTime->user,
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
                ];
            }) ?? null,
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
                        'specificationCategories' => $submodel->specificationCategories->map(function ($category) {
                            return [
                                'id' => $category->id,
                                'name' => $category->name,
                                'specifications' => $category->specifications,
                            ];
                        }),
                    ];
                }),
            ],
        ];
    }

}
