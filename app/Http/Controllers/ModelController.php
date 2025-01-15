<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ModelColor;
use App\Models\ModelImages;
use App\Models\Models;
use App\Models\Size;
use App\Models\SubModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ModelController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $models = Models::all();
        return response()->json($models);
    }

    public function getMaterials(): \Illuminate\Http\JsonResponse
    {
        $materials = Item::whareHas('type', function ($query) {
            $query->where('name', 'Mato');
        })->get();
        return response()->json($materials);
    }

    public function show(Models $model): \Illuminate\Http\JsonResponse
    {
        return response()->json($model);
    }
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->input('data'), true);

        if (!$data) {
            return response()->json([
                'message' => 'Invalid data format',
                'error' => 'Data field is not a valid JSON string',
            ], 400);
        }

        $model = Models::create([
            'name' => $data['name'] ?? null,
            'rasxod' => (double) ($data['rasxod'] ?? 0),
        ]);

        if ($request->hasFile('images') && !empty($request->file('images'))) {
            foreach ($request->file('images') as $image) {

                $fileName = time() . '_' . $image->getClientOriginalName();
                $image->storeAs('public/images', $fileName);

                ModelImages::create([
                    'model_id' => $model->id,
                    'image' => 'images/' . $fileName,
                ]);
            }
        }

        if (!empty($data['submodels'])) {
            foreach ($data['submodels'] as $submodel) {

                $submodelCreate = SubModel::create([
                    'name' => $submodel['name'] ?? null,
                    'model_id' => $model->id,
                ]);

                if (!empty($submodel['sizes'])) {
                    foreach ($submodel['sizes'] as $size) {
                        Size::create([
                            'name' => $size,
                            'submodel_id' => $submodelCreate->id,
                        ]);
                    }
                }

                if (!empty($submodel['materials'])) {
                    foreach ($submodel['materials'] as $material) {
                        ModelColor::create([
                            'material_id' => $material,
                            'submodel_id' => $submodelCreate->id,
                        ]);
                    }
                }
            }
        }

        if ($model) {
            return response()->json([
                'message' => 'Model created successfully',
                'model' => $model,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Model creation failed',
                'error' => 'There was an error creating the model.',
            ], 500);
        }
    }

    public function update(Request $request, Models $model): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->input('data'), true);

        if (!$data) {
            return response()->json([
                'message' => 'Invalid data format',
                'error' => 'Data field is not a valid JSON string',
            ], 400);
        }
        $model->update([
            'name' => $data['name'] ?? $model->name,
            'rasxod' => (double) ($data['rasxod'] ?? $model->rasxod),
        ]);

        if ($request->hasFile('images') && !empty($request->file('images'))) {

            foreach ($request->file('images') as $image) {
                $fileName = time() . '_' . $image->getClientOriginalName();
                $image->storeAs('public/images', $fileName);

                ModelImages::create([
                    'model_id' => $model->id,
                    'image' => 'images/' . $fileName,
                ]);
            }
        }

        foreach ($model->submodels as $submodel) {
            foreach ($submodel->sizes as $size) {
                $size->delete();
            }
            foreach ($submodel->modelColors as $color) {
                $color->delete();
            }
            $submodel->delete();
        }

        if (!empty($data['submodels'])) {
            foreach ($data['submodels'] as $submodel) {

                $submodelCreate = SubModel::create([
                    'name' => $submodel['name'] ?? null,
                    'model_id' => $model->id,
                ]);

                if (!empty($submodel['sizes'])) {
                    foreach ($submodel['sizes'] as $size) {
                        Size::create([
                            'name' => $size,
                            'submodel_id' => $submodelCreate->id,
                        ]);
                    }
                }

                if (!empty($submodel['materials'])) {
                    foreach ($submodel['materials'] as $material) {
                        ModelColor::create([
                            'color_id' => $material,
                            'submodel_id' => $submodelCreate->id,
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Model updated successfully',
            'model' => $model,
        ]);
    }


    public function destroy(Models $model): \Illuminate\Http\JsonResponse
    {
        $model->delete();
        return response()->json([
            'message' => 'Model deleted successfully',
        ]);
    }

    public function destroyImage(ModelImages $modelImage): \Illuminate\Http\JsonResponse
    {
        $modelImage->delete();
        return response()->json([
            'message' => 'Image deleted successfully',
        ]);
    }
}
