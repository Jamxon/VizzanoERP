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
            'tarifications' => $this->orderSubmodel->tarificationCategories
                ->flatMap(function ($cat) {
                    return $cat->tarifications;
                })
                ->filter()
                ->values(),

        ];
    }
}