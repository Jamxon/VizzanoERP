<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $category
 * @property mixed $cut_at
 * @property mixed $quantity
 */
class GetOrderCutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'submodel' => $this->category->submodel,
            'category' => $this->category->makeHidden('submodel'),
            'quantity' => $this->quantity,
            'cut_at' => $this->cut_at,
        ];
    }
}
