<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetRecipesResource;
use App\Models\ModelColor;
use App\Models\Recipe;
use App\Models\Size;
use App\Models\SubModel;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function show(Request $request)
    {
        $recipe = Recipe::where('model_color_id', $request->model_color_id)
            ->where('size_id', $request->size_id)
            ->orderBy('updated_at', 'desc')
            ->get();
        return response()->json($recipe);
    }

    public function getRecipe(Request $request)
    {
        $query = Recipe::with(['item.unit', 'item.color', 'item.type'])
            ->where('model_color_id', $request->model_color_id)
            ->where('size_id', $request->size_id);

        if (!$query->exists()) {
            return response()->json([
                'error' => 'Recipe not found'
            ], 404);
        }

        $totalSum = $query->get()->sum(function ($recipe) {
            return $recipe->item->price * $recipe->quantity;
        });

        $recipes = $query->orderBy('updated_at', 'desc')->get();

        // Relationlarni bo'shatish
        $recipes->each(function ($recipe) {
            $recipe->setRelations([]);
        });

        $modelColor = ModelColor::find($request->model_color_id);
        $modelColor->setRelations([]);
        $modelColor->load('color');

        $submodel = $modelColor->submodel;
        $submodel->setRelations([]);

        $model = $submodel->model;
        $model->setRelations([]);

        $size = Size::find($request->size_id);
        $size->setRelations([]);

        $resource = GetRecipesResource::collection($recipes);

        return response()->json([
            'recipes' => $resource,
            'total_sum' => $totalSum,
            'submodel' => $submodel->name,
            'model'  => $model,
            'model_color'  => $modelColor,
            'size'  => $size,
        ]);
    }



    public function store(Request $request)
    {
        $request->validate([
            'item_id' => 'required',
            'quantity' => 'required',
            'model_color_id' => 'required',
            'size_id' => 'required',
        ]);

        $recipe = Recipe::create([
            'item_id' => $request->item_id,
            'quantity' => $request->quantity,
            'model_color_id' => $request->model_color_id,
            'size_id' => $request->size_id,
        ]);

        if ($recipe) {
            return response()->json([
                'message' => 'Recipe created successfully',
                'recipe' => $recipe,
            ]);
        } else {
            return response()->json([
                'message' => 'Recipe not created',
                'error' => $recipe->errors(),
            ]);
        }
    }

    public function update(Request $request, Recipe $recipe)
    {
        $recipe->update($request->all());
        return response()->json([
            'message' => 'Recipe updated successfully',
            'recipe' => $recipe,
        ]);
    }

    public function destroy(Recipe $recipe)
    {
        $recipe->delete();
        return response()->json([
            'message' => 'Recipe deleted successfully',
        ]);
    }
}
