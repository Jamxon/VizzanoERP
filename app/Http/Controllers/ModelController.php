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
        // Asosiy ma'lumotlarni olish
        return $data = $request->input('data');

        // Model yaratish
        $model = Models::create([
            'name' => $data['name'] ?? null,
            'rasxod' => (double) ($data['rasxod'] ?? 0),
        ]);

        // Suratlarni saqlash (agar mavjud bo'lsa)
        if ($request->hasFile('images') && !empty($request->images)) {
            foreach ($request->images as $image) {
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
