<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
           'size_id' => 'required',
            'item_id' => 'required',
            'quantity' => 'required',
            'color_id' => 'required',
        ]);

        $recipe = Recipe::create([
            'size_id' => $request->size_id,
            'item_id' => $request->item_id,
            'quantity' => $request->quantity,
            'color_id' => $request->color_id,
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
}
