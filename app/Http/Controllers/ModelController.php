<?php

namespace App\Http\Controllers;

use App\Models\Models;
use App\Models\SubModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ModelController extends Controller
{
    public function index()
    {
        $models = Models::all();
        return response()->json($models);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);
        $model = Models::create([
            'name' => $request->name,
        ]);

        foreach ($request->submodels as $submodel) {
            SubModel::create([
                'name' => $submodel,
                'model_id' => $model->id,
            ]);
        }

        if ($model) {
            return response()->json([
                'message' => 'Model created successfully',
                'model' => $model,
            ]);
        } else {
            return response()->json([
                'message' => 'Model not created',
                'error' => $model->errors(),
            ]);
        }
    }
    public function update(Request $request, Models $model)
    {
        $model->update($request->all());
        return response()->json([
            'message' => 'Model updated successfully',
            'model' => $model,
        ]);
    }
    public function destroy(Models $model)
    {
        $model->delete();
        return response()->json([
            'message' => 'Model deleted successfully',
        ]);
    }
}
