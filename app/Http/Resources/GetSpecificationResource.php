<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GetSpecificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'orderModel' => $this->orderModel->map(function ($orderModel) {
                return [
                    'id' => $orderModel->id,
                    'model' => $orderModel->model,
//                    'submodels' => $orderModel->submodels->each(function ($submodel) {
//                        return [
//                            'id' => $submodel->id,
//                            'submodel' => $submodel->submodel->each(function ($submodel) {
//                                return [
//                                    'id' => $submodel->id,
//                                    'name' => $submodel->name,
//                                    'specificationCategories' => $submodel->specificationCategories->each(function ($specificationCategory) {
//                                        return [
//                                            'id' => $specificationCategory->id,
//                                            'name' => $specificationCategory->name,
//                                            'specifications' => $specificationCategory->specifications->each(function ($specification) {
//                                                return [
//                                                    'id' => $specification->id,
//                                                    'name' => $specification->name,
//                                                    'code' => $specification->code,
//                                                    'quantity' => $specification->quantity,
//                                                    'comment' => $specification->comment,
//                                                ];
//                                            }),
//                                        ];
//                                    }),
//                                ];
//                            }),
//                        ];
//                    })
                ];
            }),
        ];
    }
}
