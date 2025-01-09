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
            'stok' => $this->item->stok ? [
                'item' => $this->item->stok->item,
                'quantity' => $this->item->stok->quantity,
                'min_quantity' => $this->item->stok->min_quantity,
            ] : null,
            'item' => $this->item,
            'quantity' => $this->quantity,
        ];
    }
}
