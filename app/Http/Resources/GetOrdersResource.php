<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GetOrdersResource extends JsonResource
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
            'name' => $this->name,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'order_models' => $this->orderModels->map(function ($model) {
                return [
                    'id' => $model->id,
                    'name' => $model->name,
                    'quantity' => $model->pivot->quantity, // Pivot jadvaldan 'quantity' ustuni
                ];
            }),
        ];

    }
}
