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
            'orderModel' => $this->orderModel ? [
                'id' => $this->orderModel->id,
                'model' => $this->orderModel->model,
                'material' => $this->orderModel->material,
                'rasxod' => $this->orderModel->rasxod,
                'sizes' => $this->orderModel->sizes->map(function ($size) {
                    return [
                        'size' => $size->size,
                        'quantity' => $size->quantity,
                    ];
                }),
                'submodels' => $this->orderModel->submodels->map(function ($submodel) {
                    return [
                        'submodel' => $submodel->submodel,
                        'group' => $submodel->group && isset($submodel->group->group) ? [
                            'id' => $submodel->group->group->id,
                            'name' => $submodel->group->group->name,
                        ] : null,
                    ];
                }),
            ] : null,
        ];
    }
}
