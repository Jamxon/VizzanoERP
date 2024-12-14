<?php

namespace App\Http\Controllers;

use App\Models\Razryad;
use Illuminate\Http\Request;

class RazryadController extends Controller
{
    public function index()
    {
        $razryads = Razryad::all();
        return response()->json($razryads);
    }

    public function store(Request $request)
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

    public function update(Request $request, Razryad $razryad)
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

    public function destroy(Razryad $razryad)
    {
        $razryad->delete();

        return response()->json([
            'message' => 'Razryad deleted successfully',
        ]);
    }
}
