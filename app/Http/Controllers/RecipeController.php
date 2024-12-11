<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
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
