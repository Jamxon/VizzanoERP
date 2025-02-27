<?php

namespace App\Http\Resources;

use App\Models\OrderRecipes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShowOrderResource extends JsonResource
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
            'order_model' => $this->orderModel ? [
                'id' => $this->orderModel->id,
                'model' => $this->orderModel->model,
                'material' => $this->orderModel->material,
                'rasxod' => $this->orderModel->rasxod,
                'sizes' => $this->orderModel->sizes->map(function ($size) {
                    return [
                        'id' => $size->id,
                        'size' => $size->size,
                        'quantity' => $size->quantity,
                    ];
                }),
                'submodels' => $this->orderModel->submodels->map(function ($submodel) {
                    return [
                        'id' => $submodel->id,
                        'submodel' => $submodel->submodel,
                        "spends" => $submodel->submodelSpend,
                        "order_recipes" => $submodel->orderRecipes->map(function ($recipe) {
                            return [
                                'id' => $recipe->id,
                                'item' => $recipe->item,
                                'quantity' => $recipe->quantity,
                            ];
                        }),
                    ];
                }),
            ] : null,
            'instructions' => $this->instructions->map(function ($instruction) {
                return [
                    'id' => $instruction->id,
                    'title' => $instruction->title,
                    'description' => $instruction->description,
                ];
            }),

        ];
    }
}
