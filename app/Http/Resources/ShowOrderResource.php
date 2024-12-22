<?php

namespace App\Http\Resources;

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
        $orderModelsArray = [];

        foreach ($this->orderModels as $orderModel) {
            $orderModelsArray[] = $orderModel;

            $orderModelSubmodels = OrderSubModel::where('order_model_id', $orderModel->id)->get();
            $orderModelSubmodelsArray = [];
            foreach ($orderModelSubmodels as $orderModelSubmodel) {
                $orderModelRecipes = OrderRecipes::where('model_color_id', $orderModelSubmodel->model_color_id)
                    ->where('size_id', $orderModelSubmodel->size_id)
                    ->where('order_id', $this->id)
                    ->get();
                $orderModelSubmodel['recipes'] = $orderModelRecipes;
                $orderModelSubmodelsArray[] = $orderModelSubmodel;
            }
            $orderModel['submodels'] = $orderModelSubmodelsArray;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'rasxod' => $this->rasxod,
            'order_models' => $orderModelsArray,
        ];
    }
}
