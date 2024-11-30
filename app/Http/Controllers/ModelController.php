<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ModelController extends Controller
{
    public function index()
    {
        $models = Model::all();
        return response()->json([
            'models' => $models,
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'color' => 'required',
        ]);
        $model = Model::create([
            'name' => $request->name,
            'color' => $request->color,
        ]);

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
    public function update(Request $request, Model $model)
    {
        $request->validate([
            'name' => 'required',
            'color' => 'required',
        ]);
        $model->name = $request->name;
        $model->color = $request->color;
        $model->save();
        return response()->json([
            'message' => 'Model updated successfully',
            'model' => $model,
        ]);
    }
    public function destroy(Model $model)
    {
        $model->delete();
        return response()->json([
            'message' => 'Model deleted successfully',
        ]);
    }
}
