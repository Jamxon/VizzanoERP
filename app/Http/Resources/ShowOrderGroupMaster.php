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
        $order = optional($this->order);
        $orderModel = optional($order->orderModel);

        return [
            'id' => $order->id,
            'name' => $order->name,
            'quantity' => $order->quantity,
            'start_date' => $order->start_date,
            'end_date' => $order->end_date,
            'rasxod' => $order->rasxod,
            'status' => $order->status,
            'comment' => $order->comment,
            'orderModel' => [
                'id' => $orderModel->id,
                'model' => [
                    'id' => optional($orderModel->model)->id,
                    'name' => optional($orderModel->model)->name,
                ],
                'material' => [
                    'id' => optional($orderModel->material)->id,
                    'name' => optional($orderModel->material)->name,
                ],
                'sizes' => optional($orderModel->sizes)->map(function ($size) {
                        return [
                            'id' => $size->id,
                            'size' => $size->size,
                            'quantity' => $size->quantity,
                        ];
                    }) ?? [],
                'submodels' => optional($orderModel->submodels)->map(function ($submodel) {
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
            'instructions' => $order->instructions,
        ];
    }


}
