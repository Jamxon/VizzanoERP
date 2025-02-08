<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GetOrderTailorResource extends JsonResource
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
            'status' => $this->status,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'rasxod' => $this->rasxod,
            'comment' => $this->comment,
            'orderModel' => $this->orderModel->map(function ($model) {
                return [
                    'id' => $model->id,
                    'model' => $model->model,
                    'material' => $model->material,
                    'rasxod' => $model->rasxod,
                    'sizes' => $model->sizes->map(function ($size) {
                        return [
                            'size' => $size->size,
                            'quantity' => $size->quantity,
                        ];
                    }),
                    'submodels' => $model->submodels->map(function ($submodel) {
                        return [
                            'submodel' => $submodel->submodel,
                            'group' => $submodel->group,
                        ];
                    }),
                ];
            }),
            'instructions' => $this->instructions->map(function ($instruction) {
                return [
                    'id' => $instruction->id,
                    'title' => $instruction->title,
                    'description' => $instruction->description,
                ];
            }),
        ];
    }
}
