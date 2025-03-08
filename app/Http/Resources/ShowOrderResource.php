<?php

namespace App\Http\Resources;

use App\Models\OrderRecipes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $orderPrintingTime
 * @property mixed $orderModel
 * @property mixed $orderCuts
 * @property mixed $instructions
 * @property mixed $id
 * @property mixed $name
 * @property mixed $quantity
 * @property mixed $status
 * @property mixed $start_date
 * @property mixed $end_date
 * @property mixed $rasxod
 * @property mixed $comment
 */

class ShowOrderResource extends JsonResource
{
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
                        'group' => $submodel->group ? [
                            'id' => $submodel->group->id,
                            'group' => $submodel->group->group,
                        ] : null,
                        'sewingOutputs' => $submodel->sewingOutputs->map(function ($output) {
                            return [
                                'id' => $output->id,
                                'quantity' => $output->quantity,
                                'time' => $output->time,
                                'comment' => $output->comment,
                            ];
                        }),
                        'otkOrderGroup' => $submodel->otkOrderGroup ? [
                            'id' => $submodel->otkOrderGroup->id,
                            'group' => $submodel->otkOrderGroup->group,
                        ] : null,
                        'qualityChecks' => $submodel->qualityChecks->map(function ($check) {
                            return [
                                'id' => $check->id,
                                'status' => $check->status,
                                'image' => url('storage/' . $check->image),
                                'comment' => $check->comment,
                                'user' => $check->user,
                                'qualityCheckDescriptions' => $check->qualityCheckDescriptions->map(function ($description) {
                                    return [
                                        'id' => $description->id,
                                        'description' => $description->qualityDescription->quality_description_id,
                                        'status' => $description->status,
                                    ];
                                }),
                            ];
                        })->toArray(),
                        'qualityChecks_status_count' => [
                            'true' => $submodel->qualityChecks->where('status', true)->count(),
                            'false' => $submodel->qualityChecks->where('status', false)->count(),
                        ],

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
            'orderPrintingTimes' => $this->orderPrintingTime ? [
                'id' => $this->orderPrintingTime->id,
                'planned_time' => $this->orderPrintingTime->planned_time,
                'actual_time' => $this->orderPrintingTime->actual_time,
                'status' => $this->orderPrintingTime->status,
                'user' => $this->orderPrintingTime->user,
                'comment' => $this->orderPrintingTime->comment,
            ] : null,
            'specification_categories' => $this->orderCuts
                ->groupBy('specification_category_id')
                ->map(function ($cuts, $categoryId) {
                    $category = $cuts->first()->category;
                    return [
                        'id' => $category?->id,
                        'name' => $category?->name,
                        'orderCuts' => $cuts->map(function ($cut) {
                            return [
                                'id' => $cut->id,
                                'cut_at' => $cut->cut_at,
                                'quantity' => $cut->quantity,
                                'status' => $cut->status,
                                'user' => $cut->user,
                            ];
                        }),
                    ];
                })->values(),
        ];
    }
}