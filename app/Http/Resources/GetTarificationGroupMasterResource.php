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
            'submodels' => $this->orderModel->submodels->map(function ($submodel) {
                return [
                    'id' => $submodel->id,
                    'name' => $submodel->name,
                    'tarification_categories' => $submodel->tarificationCategories->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'tarifications' => $category->tarifications,
                        ];
                    }),
                ];
            }),
        ];
    }



}
