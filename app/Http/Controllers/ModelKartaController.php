<?php

namespace App\Http\Controllers;

use App\Models\ModelKarta;
use Illuminate\Http\Request;

class ModelKartaController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'model_id' => 'required|exists:models,id', // model_id mavjudligini tekshiradi
            'material_name' => 'required|string|max:255',
            'image' => 'required|file|mimes:jpeg,png,jpg,avif|max:2048', // fayl turi va hajmi cheklovi
            'comment' => 'required|string|max:500',
        ]);
        if (!file_exists(public_path('images/modelkarta'))) {
            mkdir(public_path('images/modelkarta'), 0755, true);
        }

        $image = $request->file('image');
        $image_name = time() . '.' . $image->extension();

        // Faylni 'public/images/modelkarta' papkasiga saqlash
        $image->move(public_path('images/modelkarta'), $image_name);

        $model = ModelKarta::create([
            'model_id' => $request->model_id,
            'material_name' => $request->material_name,
            'image' => 'images/modelkarta/' . $image_name, // nisbiy yo'lni saqlash
            'comment' => $request->comment,
        ]);

        if ($model) {
            return response()->json([
                'message' => 'Model created successfully',
                'model' => $model,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Model not created',
            ], 500);
        }
    }

    public function update(Request $request, ModelKarta $model)
    {
        $request->validate([
            'model_id' => 'required|exists:models,id',
            'material_name' => 'required|string|max:255',
            'image' => 'file|mimes:jpeg,png,jpg,avif|max:2048',
            'comment' => 'required|string|max:500',
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $image_name = time() . '.' . $image->extension();
            $image->move(public_path('images/modelkarta'), $image_name);
            $model->image = 'images/modelkarta/' . $image_name;
        }

        $model->model_id = $request->model_id;
        $model->material_name = $request->material_name;
        $model->comment = $request->comment;
        $model->save();

        return response()->json([
            'message' => 'Model updated successfully',
            'model' => $model,
        ]);
    }

    public function destroy(ModelKarta $model)
    {
        $model->delete();
        return response()->json([
            'message' => 'Model deleted successfully',
        ]);
    }
}
