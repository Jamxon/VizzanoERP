<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::all();
        return response()->json($units);
    }

    public function show(Unit $unit)
    {
        return response()->json($unit);
    }

    public function store(Request $request)
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

    public function update(Request $request, Unit $unit)
    {
        $unit->update($request->all());
        return response()->json([
            'message' => 'Unit updated successfully',
            'unit' => $unit,
        ]);
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();
        return response()->json([
            'message' => 'Unit deleted successfully',
            'unit' => $unit,
        ]);
    }
}
