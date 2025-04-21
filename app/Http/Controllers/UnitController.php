<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $units = Unit::all();
        return response()->json($units);
    }

    public function show(Unit $unit): \Illuminate\Http\JsonResponse
    {
        return response()->json($unit);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required',
        ]);

        $unit = Unit::create([
            'name' => $request->name,
        ]);

        if ($unit) {
            return response()->json([
                'message' => 'Unit created successfully',
                'unit' => $unit,
            ]);
        } else {
            return response()->json([
                'message' => 'Unit not created',
                'error' => $unit->errors(),
            ]);
        }
    }

    public function update(Request $request, Unit $unit): \Illuminate\Http\JsonResponse
    {
        $unit->update($request->all());
        return response()->json([
            'message' => 'Unit updated successfully',
            'unit' => $unit,
        ]);
    }
}
