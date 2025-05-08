<?php

namespace App\Http\Controllers;

use App\Models\Color;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $colors = Color::all();
        return response()->json($colors);
    }

    public function show(Color $color): \Illuminate\Http\JsonResponse
    {
        return response()->json($color);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required',
            'hex' => 'required',
        ]);

        $color = Color::create([
            'name' => $request->name,
            'hex' => $request->hex,
        ]);

        if ($color) {
            return response()->json([
                'message' => 'Color created successfully',
                'color' => $color,
            ]);
        } else {
            return response()->json([
                'message' => 'Color not created',
                'error' => $color->errors(),
            ]);
        }
    }

    public function update(Request $request, Color $color): \Illuminate\Http\JsonResponse
    {
        $color->update($request->all());
        return response()->json([
            'message' => 'Color updated successfully',
            'color' => $color,
        ]);
    }

}
