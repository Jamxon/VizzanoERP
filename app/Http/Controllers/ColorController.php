<?php

namespace App\Http\Controllers;

use App\Models\Color;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    public function index()
    {
        $colors = Color::all();
        return response()->json($colors);
    }

    public function show(Color $color)
    {
        return response()->json($color);
    }

    public function store(Request $request)
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

    public function update(Request $request, Color $color)
    {
        $color->update($request->all());
        return response()->json([
            'message' => 'Color updated successfully',
            'color' => $color,
        ]);
    }

    public function destroy(Color $color)
    {
        $color->delete();
        return response()->json([
            'message' => 'Color deleted successfully',
            'color' => $color,
        ]);
    }
}
