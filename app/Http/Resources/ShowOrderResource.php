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
 * @property mixed $price
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
            'price' => $this->price,
            'season_year' => $this->season_year,
            'season_type' => $this->season_type,
            'order_model' => $this->orderModel ? [
                'id' => $this->orderModel->id,
                'model' => $this->orderModel->model ? [
                    'id' => $this->orderModel->model->id,
                    'name' => $this->orderModel->model->name,
                    'rasxod' => $this->orderModel->model->rasxod,
                    'minute' => $this->orderModel->model->minute ?? 0,
                    'images' => $this->orderModel->model->images->map(function ($image) {
                        return [
                            'id' => $image->id,
                            'image' => $image->image,
                        ];
                    })->toArray(),
                ] : null,
                'material' => $this->orderModel->material,
                'minute' => $this->orderModel->minute ?? 0,
                'rasxod' => $this->orderModel->rasxod,
                'status' => $this->orderModel->status,
                'sizes' => $this->orderModel->sizes->map(function ($size) {
                    return [
                        'id' => $size->id,
                        'size' => $size->size,
                        'quantity' => $size->quantity,
                        'color' => $size->color ?? null
                    ];
                }),
                'submodels' => $this->orderModel->submodels->map(function ($submodel) {
                    return [
                        'id' => $submodel->id,
                        'submodel' => $submodel->submodel ? [
                            'id' => $submodel->submodel->id,
                            'name' => $submodel->submodel->name,
                            "order_recipes" => $submodel->submodel->orderRecipes->map(function ($recipe) {
                                return [
                                    'id' => $recipe->id,
                                    'item' => $recipe->item,
                                    'quantity' => $recipe->quantity,
                                ];
                        }),
                        ] : null,
                        "spends" => $submodel->submodelSpend,
                        'group' => $submodel->group ? [
                            'id' => $submodel->group->id,
                            'group' => $submodel->group->group,
                        ] : null,
                        'sewingOutputs' => $submodel->sewingOutputs
                                ?->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
                                ->map(fn($sewingOutput) => [
                                    'id' => $sewingOutput->id,
                                    'quantity' => $sewingOutput->quantity,
                                    'comment' => $sewingOutput->comment,
                                    'time' => [
                                        'id' => $sewingOutput->time?->id,
                                        'time' => $sewingOutput->time->time,
                                    ],
                                ])
                                ->values()
                                ->toArray() ?? [],
                        'total_quantity' => $submodel->sewingOutputs
                                ->sum('quantity') ?? 0,
                        'otkOrderGroup' => $submodel->otkOrderGroup ? [
                            'id' => $submodel->otkOrderGroup->id,
                            'group' => $submodel->otkOrderGroup->group,
                        ] : null,
                        'qualityChecks' => $submodel->qualityChecks->map(function ($check) {
                            return [
                                'id' => $check->id,
                                'status' => $check->status,
                                'image' => url($check->image),
                                'comment' => $check->comment,
                                'user' => $check->user,
                                'qualityCheckDescriptions' => $check->qualityCheckDescriptions->map(function ($description) {
                                    return [
                                        'id' => $description->id,
                                        'description' => optional($description->qualityDescription)->description,
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
            'contragent' => $this->contragent ? [
                'id' => $this->contragent->id,
                'name' => $this->contragent->name,
                'description' => $this->contragent->description,
            ] : null,
            'packageOutcomes' => $this->packageOutcomes->map(function ($package) {
                return [
                    'id' => $package->id,
                    'package_size' => $package->package_size,
                    'package_quantity' => $package->package_quantity,
                ];
            }),
        ];
    }
}