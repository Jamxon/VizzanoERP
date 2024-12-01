<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetNormalizationResource;
use App\Models\Normalization;
use Illuminate\Http\Request;

class NormalizationController extends Controller
{
    public function index()
    {
        $data = Normalization::all();
        $resource = GetNormalizationResource::collection($data);
        return response()->json($resource);
    }

    public function store(Request $request)
    {
        $request->validate([
            'material_name' => 'required',
            'quantity' => 'required',
            'model_id' => 'required',
            'unit_id' => 'required',
        ]);
        $data = Normalization::create([
            'material_name' => $request->material_name,
            'quantity' => $request->quantity,
            'model_id' => $request->model_id,
            'unit_id' => $request->unit_id,
        ]);

        if ($data) {
            return response()->json([
                'message' => 'Data created successfully',
                'data' => $data,
            ]);
        } else {
            return response()->json([
                'message' => 'Data not created',
                'error' => $data->errors(),
            ]);
        }
    }
    public function update(Request $request, Normalization $normalization)
    {
        $request->validate([
            'material_name' => 'required',
            'quantity' => 'required',
            'model_id' => 'required',
            'unit_id' => 'required',
        ]);
        $normalization->material_name = $request->material_name;
        $normalization->quantity = $request->quantity;
        $normalization->model_id = $request->model_id;
        $normalization->unit_id = $request->unit_id;
        $normalization->save();
        return response()->json([
            'message' => 'Data updated successfully',
            'data' => $normalization,
        ]);
    }

    public function destroy(Normalization $normalization)
    {
        $normalization->delete();
        return response()->json([
            'message' => 'Data deleted successfully',
        ]);
    }
}
