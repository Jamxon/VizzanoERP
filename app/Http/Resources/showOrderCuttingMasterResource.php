<?php

namespace App\Http\Resources;

use AllowDynamicProperties;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

#[AllowDynamicProperties] class showOrderCuttingMasterResource extends JsonResource
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
            'quantity' => $this->quantity,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'comment' => $this->comment,
            'orderModel' => $this->whenLoaded('orderModel', function () {
                return $this->orderModel->load('model', 'submodels', 'submodels.submodel', 'sizes.size');
            }),
            'instructions' => $this->whenLoaded('instructions', function () {
                return $this->instructions;
            }),
            'orderRecipes' => $this->whenLoaded('orderRecipes', function () {
                return $this->orderRecipes;
            }),
            'orderPrintingTime' => $this->whenLoaded('orderPrintingTime', function () {
                return $this->orderPrintingTime->load('user');
            }),
            'outcomes' => $this->outcomes,
        ];
    }

}
