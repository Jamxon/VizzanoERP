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
                    'id' => $this->orderModel->submodels->id,
                    'submodel' => $this->orderModel->submodels->submodel,
                    'tarification_categories' => $this->orderModel->submodels->tarificationCategories->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'tarifications' => $category->tarifications,
                        ];
                    }),
                ];
    }



}
