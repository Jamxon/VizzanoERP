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
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'rasxod' => $this->rasxod,
            'status' => $this->status,
            'comment' => $this->comment,
            'orderModel' => $this->orderModel ? [
                'id' => $this->orderModel->id,
                'model' => [
                    'id' => $this->orderModel->model?->id,
                    'name' => $this->orderModel->model?->name,
                ],
                'material' => [
                    'id' => $this->orderModel->material?->id,
                    'name' => $this->orderModel->material?->name,
                ],
                'sizes' => $this->orderModel->sizes?->map(fn($size) => [
                        'id' => $size->id,
                        'size' => $size->size,
                        'quantity' => $size->quantity,
                    ]) ?? [],
                'submodels' => $this->orderModel->submodels?->map(fn($submodel) => [
                        'id' => $submodel->id,
                        'submodel' => $submodel->submodel,
                        'tarificationCategories' => $submodel->tarificationCategories?->map(fn($category) => [
                                'id' => $category->id,
                                'name' => $category->name,
                                'tarifications' => $category->tarifications,
                            ]) ?? [],
                        'sewingOutputs' => $submodel->sewingOutputs?->map(fn($sewingOutput) => [
                                'id' => $sewingOutput->id,
                                'quantity' => $sewingOutput->quantity,
                                'time' => [
                                    'id' => $sewingOutput->time?->id,
                                    'name' => $sewingOutput->time?->time,
                                ],
                            ]) ?? [],
                    ]) ?? [],
            ] : null,
            'instructions' => $this->instructions,
        ];
    }

}
