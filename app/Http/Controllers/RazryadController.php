<?php

namespace App\Http\Controllers;

use App\Models\Razryad;
use Illuminate\Http\Request;

class RazryadController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $razryads = Razryad::all();
        return response()->json($razryads);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required',
            'salary' => 'required',
        ]);

        $razryad = Razryad::create([
            'name' => $request->name,
            'salary' => $request->salary,
        ]);

        if ($razryad) {
            return response()->json([
                'message' => 'Razryad created successfully',
                'razryad' => $razryad,
            ]);
        } else {
            return response()->json([
                'message' => 'Razryad not created',
                'error' => $razryad->errors(),
            ]);
        }
    }

    public function update(Request $request, Razryad $razryad): \Illuminate\Http\JsonResponse
    {
        $razryad->update($request->all());

        if ($razryad) {
            return response()->json([
                'message' => 'Razryad updated successfully',
                'razryad' => $razryad,
            ]);
        } else {
            return response()->json([
                'message' => 'Razryad not updated',
                'error' => $razryad->errors(),
            ]);
        }
    }

    public function destroy(Razryad $razryad): \Illuminate\Http\JsonResponse
    {
        $razryad->delete();

        return response()->json([
            'message' => 'Razryad deleted successfully',
        ]);
    }
}
