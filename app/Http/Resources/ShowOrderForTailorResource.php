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
            'tarification' => $this->orderSubmodel->tarificationCategories->tarification ?? null,
        ];
    }
}