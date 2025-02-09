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
        $submodel = optional($this->order->orderModel->submodels->first()); // Birinchi submodelni olamiz
        $tarificationCategory = optional($submodel->tarificationCategories->first()); // Birinchi tarificationCategoryni olamiz

        return [
            'id' => $tarificationCategory->id ?? null,
            'name' => $tarificationCategory->name ?? null,
            'tarifications' => $tarificationCategory->tarifications ?? [],
        ];
    }


}
