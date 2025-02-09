<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GetTarificationGroupMasterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => optional($this->order->orderModel->submodels->first())->tarificationCategories->id ?? null,
            'name' => optional($this->order->orderModel->submodels->first())->tarificationCategories->name ?? null,
            'tarifications' => optional($this->order->orderModel->submodels->first())->tarificationCategories->tarifications ?? [],
        ];
    }

}
