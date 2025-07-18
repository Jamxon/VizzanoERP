<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShowOrderForTailorResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'model' => $this->order->orderModel->model,
            'submodel' => $this->orderSubmodel->submodel,
            'group' => $this->group,
            'tarification_categories' => $this->tarificationCategories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'tarification' => $category->tarification ?? null,
                ];
            }) ?? [],
        ];
    }
}