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
                'submodels' => $this->orderModel->submodels->map(function ($submodel) {
                    $recipes = OrderRecipes::where('submodel_id', $submodel->submodel->id)
                        ->where('order_id', $this->id)
                        ->get();

                    return [
                        'id' => $submodel->id,
                        'submodel' => $submodel->submodel,
                        "spends" => $submodel->submodelSpend,
                        'recipes' => $recipes->map(function ($recipe) {
                            return [
                                'id' => $recipe->id,
                                'item' => $recipe->item ? [
                                    'id' => $recipe->item->id,
                                    'name' => $recipe->item->name,
                                    'unit' => $recipe->item->unit ? [
                                        'id' => $recipe->item->unit->id,
                                        'name' => $recipe->item->unit->name,
                                    ] : null,
                                    'quantity' => $recipe->item->quantity,
                                    'price' => $recipe->item->price,
                                    'code' => $recipe->item->code,
                                ] : null,
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
