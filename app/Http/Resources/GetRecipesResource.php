<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GetRecipesResource extends JsonResource
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
            'name' => $this->item->name,
            'price' => $this->item->price,
            'total_price' => $this->getTotalPriceAttribute(),
            'quantity' => $this->quantity,
            'code' => $this->item->code,
            'image' => $this->item->image,
            'unit' => $this->item->unit->name,
            'item_color' => $this->item->color->name,
            'type' => $this->item->type->name,
        ];
    }
}
