<?php

namespace App\Http\Controllers;

use App\Models\ModelColor;
use App\Models\ModelImages;
use App\Models\Models;
use App\Models\Size;
use App\Models\SubModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModelController extends Controller
{
    public function index()
    {
        $models = Models::all();
        return response()->json($models);
    }

    public function show(Models $model)
    {
        return response()->json($model);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $model = Models::create([
            'name' => $request->name,
            'rasxod' => $request->rasxod ?? 0
        ]);

        if ($request->has('images')) {
            foreach ($request->images as $image) {

                $fileName = time() . '_' . $image->getClientOriginalName();

                $image->storeAs('public/images', $fileName);

                ModelImages::create([
                    'model_id' => $model->id,
                    'image' => 'images/' . $fileName,
                ]);
            }
        }

        if ($request->has('submodels') || !empty($request->submodels)) {
            foreach ($request->submodels as $submodel) {
                $submodelCreate =  SubModel::create([
                    'name' => $submodel['name'],
                    'model_id' => $model->id,
                ]);

                foreach ($submodel['sizes'] as $size) {
                    Size::create([
                        'name' => $size,
                        'submodel_id' => $submodelCreate->id,
                    ]);
                }
                foreach ($submodel['colors'] as $color) {
                    ModelColor::create([
                        'color_id' => $color,
                        'submodel_id' => $submodelCreate->id,
                    ]);
                }
            }
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
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
        ]);

        $model = Models::findOrFail($id);
        $model->update([
            'name' => $request->name,
        ]);

        if ($request->has('submodels')){
            foreach ($model->submodels as $submodel) {
                foreach ($submodel->sizes as $size) {
                    $size->delete();
                }
                foreach ($submodel->modelColors as $color) {
                    $color->delete();
                }
                $submodel->delete();
            }
        }

        if ($request->has('images')) {
            foreach ($request->images as $image) {

                $fileName = time() . '_' . $image->getClientOriginalName();

                $image->storeAs('public/images', $fileName);

                ModelImages::create([
                    'model_id' => $model->id,
                    'image' => 'images/' . $fileName,
                ]);
            }
        }

        if ($request->has('submodels')){
            foreach ($request->submodels as $submodel) {
                $submodelCreate = SubModel::create([
                    'name' => $submodel['name'],
                    'model_id' => $model->id,
                ]);

                foreach ($submodel['sizes'] as $size) {
                    Size::create([
                        'name' => $size,
                        'submodel_id' => $submodelCreate->id,
                    ]);
                }

                foreach ($submodel['colors'] as $color) {
                    ModelColor::create([
                        'color_id' => $color,
                        'submodel_id' => $submodelCreate->id,
                    ]);
                }
            }
        }

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

    public function destroyImage(ModelImages $image)
    {
        $image->delete();
        return response()->json([
            'message' => 'Image deleted successfully',
        ]);
    }
}
