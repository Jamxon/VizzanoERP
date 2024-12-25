<?php

namespace App\Http\Controllers;

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
        // `data` maydonini JSON sifatida dekodlash
        $data = json_decode($request->input('data'), true);

        if (!$data) {
            return response()->json([
                'message' => 'Invalid data format',
                'error' => 'Data field is not a valid JSON string',
            ], 400);
        }

        // Model yaratish
        $model = Models::create([
            'name' => $data['name'] ?? null,
            'rasxod' => (double) ($data['rasxod'] ?? 0),
        ]);

        // Suratlarni saqlash (agar mavjud bo'lsa)
        if ($request->hasFile('images') && !empty($request->file('images'))) {
            foreach ($request->file('images') as $image) {
                // Faylni nomini o'zgartirib saqlash
                $fileName = time() . '_' . $image->getClientOriginalName();
                $image->storeAs('public/images', $fileName);

                // Rasm ma'lumotlarini saqlash
                ModelImages::create([
                    'model_id' => $model->id,
                    'image' => 'images/' . $fileName,
                ]);
            }
        }

        // Submodel va uning rang va o'lchamlarini saqlash (agar mavjud bo'lsa)
        if (!empty($data['submodels'])) {
            foreach ($data['submodels'] as $submodel) {
                // Submodelni yaratish
                $submodelCreate = SubModel::create([
                    'name' => $submodel['name'] ?? null,
                    'model_id' => $model->id,
                ]);

                // O'lchamlarni saqlash
                if (isset($submodel['sizes']) && !empty($submodel['sizes'])) {
                    foreach ($submodel['sizes'] as $size) {
                        Size::create([
                            'name' => $size,
                            'submodel_id' => $submodelCreate->id,
                        ]);
                    }
                }

                // Ranglarni saqlash
                if (isset($submodel['colors']) && !empty($submodel['colors'])) {
                    foreach ($submodel['colors'] as $color) {
                        ModelColor::create([
                            'color_id' => $color,
                            'submodel_id' => $submodelCreate->id,
                        ]);
                    }
                }
            }
        }

        // Model muvaffaqiyatli yaratilganini qaytarish
        if ($model) {
            return response()->json([
                'message' => 'Model created successfully',
                'model' => $model,
            ], 201);
        } else {
            // Xato holat
            return response()->json([
                'message' => 'Model creation failed',
                'error' => 'There was an error creating the model.',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        dd($request->all());
        // `data` maydonini JSON sifatida dekodlash
        $data = json_decode($request->input('data'), true);

        if (!$data) {
            return response()->json([
                'message' => 'Invalid data format',
                'error' => 'Data field is not a valid JSON string',
            ], 400);
        }

        // Modelni topish
        $model = Models::findOrFail($id);

        // Modelni yangilash
        $model->update([
            'name' => $data['name'] ?? $model->name,
            'rasxod' => (double) ($data['rasxod'] ?? $model->rasxod),
        ]);

        // Eski suratlarni o'chirish va yangi suratlarni saqlash
        if ($request->hasFile('images') && !empty($request->file('images'))) {
            // Eski suratlarni o'chirish
            foreach ($model->images as $oldImage) {
                // Faylni tizimdan o'chirish (ixtiyoriy)
                Storage::delete('public/' . $oldImage->image);
                $oldImage->delete();
            }

            // Yangi suratlarni saqlash
            foreach ($request->file('images') as $image) {
                $fileName = time() . '_' . $image->getClientOriginalName();
                $image->storeAs('public/images', $fileName);

                ModelImages::create([
                    'model_id' => $model->id,
                    'image' => 'images/' . $fileName,
                ]);
            }
        }

        // Eski submodellarga oid ma'lumotlarni o'chirish
        foreach ($model->submodels as $submodel) {
            foreach ($submodel->sizes as $size) {
                $size->delete();
            }
            foreach ($submodel->modelColors as $color) {
                $color->delete();
            }
            $submodel->delete();
        }

        // Yangi submodellarga oid ma'lumotlarni saqlash
        if (!empty($data['submodels'])) {
            foreach ($data['submodels'] as $submodel) {
                // Submodelni yaratish
                $submodelCreate = SubModel::create([
                    'name' => $submodel['name'] ?? null,
                    'model_id' => $model->id,
                ]);

                // O'lchamlarni saqlash
                if (isset($submodel['sizes']) && !empty($submodel['sizes'])) {
                    foreach ($submodel['sizes'] as $size) {
                        Size::create([
                            'name' => $size,
                            'submodel_id' => $submodelCreate->id,
                        ]);
                    }
                }

                // Ranglarni saqlash
                if (isset($submodel['colors']) && !empty($submodel['colors'])) {
                    foreach ($submodel['colors'] as $color) {
                        ModelColor::create([
                            'color_id' => $color,
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
