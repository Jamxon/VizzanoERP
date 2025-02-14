<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Materials;
use App\Models\ModelImages;
use App\Models\Models;
use App\Models\Size;
use App\Models\SubModel;
use Illuminate\Http\Request;

class ModelController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $models = Models::with([
            'sizes',
            'submodels',
            'materials',
            'images'
        ])->get();

        return response()->json($models);
    }

    public function getMaterials(): \Illuminate\Http\JsonResponse
    {
        $materials = Item::whereHas('type', function ($query) {
            $query->where('name', 'Mato');
        })->get();
        return response()->json($materials);
    }

    public function show(Models $model): \Illuminate\Http\JsonResponse
    {
        $model->load(['sizes', 'submodels', 'images']);
        return response()->json($model);
    }
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->data, true);

        if (!is_array($data) || empty($data)) {
            return response()->json([
                'message' => 'Invalid data format',
                'error' => 'Data field is not a valid array',
            ], 400);
        }

        $model = Models::create([
            'name' => $data['name'] ?? null,
            'rasxod' => (double)($data['rasxod'] ?? 0),
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

        if (!empty($data['sizes'])) {
            foreach ($data['sizes'] as $size) {
                Size::create([
                    'name' => $size,
                    'model_id' => $model->id,
                ]);
            }
        }

        if (!empty($data['submodels'])) {
            foreach ($data['submodels'] as $submodel) {
                SubModel::create([
                    'name' => $submodel ?? null,
                    'model_id' => $model->id,
                ]);
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
        $data = json_decode($request->data, true);

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

        if (!empty($data['sizes'])) {
            foreach ($data['sizes'] as $sizeId) {
                $size = Size::find($sizeId);
                if ($size && $size->model_id == $model->id) {
                    $size->update([
                        'model_id' => $model->id,
                        'name' => $size->name,
                    ]);
                } else {
                    Size::create([
                        'name' => 'default_name',
                        'model_id' => $model->id,
                    ]);
                }
            }
        }

        if (!empty($data['submodels'])) {
            foreach ($data['submodels'] as $submodelId) {
                $submodel = SubModel::find($submodelId);
                if ($submodel && $submodel->model_id == $model->id) {
                    $submodel->update([
                        'model_id' => $model->id,
                        'name' => $submodel->name,
                    ]);
                } else {
                    SubModel::create([
                        'name' => 'default_name',
                        'model_id' => $model->id,
                    ]);
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
