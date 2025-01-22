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
            'rasxod' => $this->rasxod,
            'comment' => $this->comment,
            'orderModel' => $this->orderModel,
            'instructions' => $this->instructions,
            'branch' => $this->branch,
            'contragent' => $this->contragent,
            'orderRecipes' => $this->orderRecipes,
            'orderPrintingTime' => $this->orderPrintingTime,
            'outcomes' => $this->orderModel->outcomeItemModelDistributions->outcomeItem->outcome,
        ];
    }
}
