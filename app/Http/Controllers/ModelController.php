<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Log;
use App\Models\Materials;
use App\Models\ModelImages;
use App\Models\Models;
use App\Models\Size;
use App\Models\SubModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModelController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $models = Models::where('branch_id', auth()->user()->employee->branch_id)
        ->with([
            'sizes',
            'submodels',
            'images'
        ])
            ->orderBy('updated_at', 'desc')
            ->get();

        $resource = $models->map(function ($model) {
            return [
                'id' => $model->id,
                'name' => $model->name,
                'rasxod' => $model->rasxod,
                'description' => $model->description,
                'sizes' => $model->sizes->map(function ($size) {
                    return [
                        'id' => $size->id,
                        'name' => $size->name,
                    ];
                }),
                'submodels' => $model->submodels->map(function ($submodel) {
                    return [
                        'id' => $submodel->id,
                        'name' => $submodel->name,
                    ];
                }),
                'images' => $model->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'image' => $image->image,
                    ];
                }),
            ];
        });

        return response()->json($resource);
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

    public function store(Request $request)
    {
       return  $request->all();
        try {
            $data = json_decode($request->data, true);

            if (!is_array($data) || empty($data)) {
                return response()->json([
                    'message' => 'Invalid data format',
                    'error' => 'Data field is not a valid array',
                ], 400);
            }

            // ✅ Urinish logi
            Log::add(auth()->id(), 'Model yaratishga urinish qilindi', 'attempt', $data);

            try {
                $model = Models::create([
                    'name' => $data['name'] ?? null,
                    'rasxod' => (double)($data['rasxod'] ?? 0),
                    'branch_id' => auth()->user()->employee->branch_id,
                    'description' => $data['description'] ?? null,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Model creation failed',
                    'error' => $e->getMessage(),
                ], 500);
            }

                try {
                    $index = 1;
                    while ($request->hasFile('images' . $index)) {
                        $image = $request->file('images' . $index);
                        $fileName = time() . '_' . $image->getClientOriginalName();
                        $image->storeAs('/images/', $fileName);

                        ModelImages::create([
                            'model_id' => $model->id,
                            'image' => 'images/' . $fileName,
                        ]);

                        $index++;
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Image upload failed',
                        'error' => $e->getMessage(),
                    ], 500);
                }

            if (!empty($data['sizes'])) {
                try {
                    foreach ($data['sizes'] as $size) {
                        Size::create([
                            'name' => $size['name'],
                            'model_id' => $model->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Size creation failed',
                        'error' => $e->getMessage(),
                    ], 500);
                }
            }

            if (!empty($data['submodels'])) {
                try {
                    foreach ($data['submodels'] as $submodel) {
                        SubModel::create([
                            'name' => $submodel['name'] ?? null,
                            'model_id' => $model->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Submodel creation failed',
                        'error' => $e->getMessage(),
                    ], 500);
                }
            }

            // ✅ Muvaffaqiyatli log
            Log::add(auth()->id(), 'Yangi model yaratildi', 'create', null, $model->toArray());

            return response()->json([
                'message' => 'Model created successfully',
                'model' => $model,
            ], 201);

        } catch (\Exception $e) {
            Log::add(auth()->id(), 'Model yaratishda umumiy xatolik', 'attempt', $request->all(), ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Models $model)
    {
//        return $request->all();

        $data = json_decode($request->data, true);

        if (!is_array($data) || empty($data)) {
            Log::add( auth()->id(),'Model yangilanishida xatolik', 'error', $data, ['error' => 'Data field is not a valid array']);
            return response()->json([
                'message' => 'Invalid data format',
                'error' => 'Data field is not a valid array',
            ], 400);
        }

        Log::add(auth()->id(), 'Model yangilanishiga urinish qilindi', 'attempt', $data);

        DB::beginTransaction();

        try {
            $oldModel = $model->toArray();

            $model->update([
                'name' => $data['name'] ?? $model->name,
                'rasxod' => (double) ($data['rasxod'] ?? $model->rasxod),
                'description' => $data['description'] ?? null,
            ]);

            $index = 1;
            while ($request->hasFile('images' . $index)) {
                $image = $request->file('images' . $index);
                $fileName = time() . '_' . $image->getClientOriginalName();
                $image->storeAs('/images/', $fileName);

                ModelImages::create([
                    'model_id' => $model->id,
                    'image' => 'images/' . $fileName,
                ]);

                $index++;
            }

            if (!empty($data['sizes'])) {
                foreach ($data['sizes'] as $sizeData) {
                    Size::updateOrCreate(
                        ['id' => $sizeData['id'], 'model_id' => $model->id],
                        ['name' => $sizeData['name']]
                    );
                }
            }

            if (!empty($data['submodels'])) {
                foreach ($data['submodels'] as $submodelData) {
                    SubModel::updateOrCreate(
                        ['id' => $submodelData['id'], 'model_id' => $model->id],
                        ['name' => $submodelData['name']]
                    );
                }
            }

            DB::commit();

            Log::add(auth()->id(), 'Model muvaffaqiyatli yangilandi', 'edit', $oldModel, $model->toArray());

            return response()->json([
                'message' => 'Model updated successfully',
                'model' => $model->load(['sizes', 'submodels', 'images']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::add(auth()->id(), 'Xatolik yuz berdi: Model yangilanishi muvaffaqiyatsiz', 'error', $data, $e->getMessage());

            return response()->json([
                'message' => 'Model update failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Models $model): \Illuminate\Http\JsonResponse
    {
        // ✅ Urinish logi
        Log::add(auth()->id(), 'Model o‘chirishga urinish qilindi', 'attempt', ['model_id' => $model->id]);

        try {
            $model->delete();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Model deletion failed',
                'error' => $e->getMessage(),
            ], 500);
        }

        // ✅ Muvaffaqiyatli o‘chirish logi
        Log::add(auth()->id(), 'Model muvaffaqiyatli o‘chirildi', 'delete', ['model_id' => $model->id]);

        return response()->json([
            'message' => 'Model deleted successfully',
        ]);
    }

    public function destroyImage(ModelImages $modelImage): \Illuminate\Http\JsonResponse
    {
        // ✅ Urinish logi
        Log::add(auth()->id(), 'Image o‘chirishga urinish qilindi', 'attempt', ['image_id' => $modelImage->id]);

        try {
            $modelImage->delete();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Image deletion failed',
                'error' => $e->getMessage(),
            ], 500);
        }

        // ✅ Muvaffaqiyatli o‘chirish logi
        Log::add(auth()->id(), 'Image muvaffaqiyatli o‘chirildi', 'delete', ['image_id' => $modelImage->id]);

        return response()->json([
            'message' => 'Image deleted successfully',
        ]);
    }
}