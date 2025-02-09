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
            'id' => $this->orderModel->submodels->tarificationCategories->id,
            'name' => $this->orderModel->submodels->tarificationCategories->name,
            'tarifications' => $this->orderModel->submodels->tarificationCategories->tarifications,
        ];
    }
}
