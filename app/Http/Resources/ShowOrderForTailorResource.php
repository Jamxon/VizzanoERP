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
            'order_id' => $this->order->id,
            'model' => $this->order->orderModel->model,
            'submodel' => $this->orderSubmodel->submodel,
            'group' => $this->group,
            'status' => $this->order->status,
            'tarification_categories' => $this->orderSubmodel->tarificationCategories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'tarification' => $category->tarifications ?? null,
                ];
            }) ?? [],
        ];
    }
}