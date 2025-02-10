<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShowOrderGroupMaster extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->order->id ?? null,
            'name' => $this->order->name ?? null,
            'quantity' => $this->order->quantity ?? null,
            'start_date' => $this->order->start_date ?? null,
            'end_date' => $this->order->end_date ?? null,
            'rasxod' => $this->order->rasxod ?? null,
            'status' => $this->order->status ?? null,
            'comment' => $this->order->comment ?? null,
            'orderModel' => [
                'id' => optional($this->order->orderModel)->id,
                'model' => [
                    'id' => optional($this->order->orderModel->model)->id,
                    'name' => optional($this->order->orderModel->model)->name,
                ],
                'material' => [
                    'id' => optional($this->order->orderModel->material)->id,
                    'name' => optional($this->order->orderModel->material)->name,
                ],
                'sizes' => optional($this->order->orderModel->sizes)->map(function ($size) {
                        return [
                            'id' => $size->id,
                            'size' => $size->size,
                            'quantity' => $size->quantity,
                        ];
                    }) ?? [],
                'submodels' => optional($this->order->orderModel->submodels)->map(function ($submodel) {
                        return [
                            'id' => $submodel->id,
                            'submodel' => $submodel->submodel,
                            'tarificationCategories' => optional($submodel->tarificationCategories)->map(function ($category) {
                                    return [
                                        'id' => $category->id,
                                        'name' => $category->name,
                                        'tarifications' => $category->tarifications,
                                    ];
                                }) ?? [],
                        ];
                    }) ?? [],
            ],
            'instructions' => $this->order->instructions ?? null,
        ];
    }

}
