<?php

namespace App\Http\Resources;

use App\Models\OrderGroup;
use App\Models\OrderRecipes;
use App\Models\OrderSubModel;
use App\Models\SubModel;
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
            'order_models' => $this->orderModels->map(function ($orderModel) {
                return [
                    'id' => $orderModel->id,
                    'model' => $orderModel->model,
                    'material' => $orderModel->material,
                    'sizes' => $orderModel->sizes->map(function ($size) {
                        return [
                            'id' => $size->id,
                            'size' => $size->size,
                            'quantity' => $size->pivot->quantity,
                        ];
                    }),
                    'submodels' => $orderModel->submodels->map(function ($submodel) {
                        return [
                            'id' => $submodel->id,
                            'submodel' => $submodel->submodel,
                        ];
                    }),
                ];
            }),
        ];
    }
}
