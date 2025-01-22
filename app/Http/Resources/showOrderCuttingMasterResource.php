<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class showOrderCuttingMasterResource extends JsonResource
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
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'comment' => $this->comment,
            'orderModel' => $this->orderModel->load('model', 'submodels', 'submodels.submodel', 'sizes.size'),
            'instructions' => $this->instructions,
            'orderRecipes' => $this->orderRecipes,
            'orderPrintingTime' => $this->orderPrintingTime,
            'outcomes' => $this->outcomes,
        ];
    }

}
