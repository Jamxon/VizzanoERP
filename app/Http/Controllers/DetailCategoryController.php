<?php

namespace App\Http\Controllers;

use App\Models\DetailCategory;
use Illuminate\Http\Request;

class DetailCategoryController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $detailCategories = DetailCategory::all();
        return response()->json($detailCategories);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $detailCategory = DetailCategory::create([
            'name' => $request->name,
        ]);

        if ($detailCategory){
            return response()->json([
                'message' => 'Detail Category created successfully',
                'detailCategory' => $detailCategory,
            ], 201);
        } else {
            return response()->json([
                'message' => 'Detail Category not created',
                'error' => $detailCategory->errors(),
            ], 400);
        }
    }

    public function update(Request $request, DetailCategory $detailCategory): \Illuminate\Http\JsonResponse
    {
        $detailCategory->update($request->all());

        if ($detailCategory){
            return response()->json([
                'message' => 'Detail Category updated successfully',
                'detailCategory' => $detailCategory,
            ]);
        } else {
            return response()->json([
                'message' => 'Detail Category not updated',
                'error' => $detailCategory->errors(),
            ]);
        }
    }

    public function destroy(DetailCategory $detailCategory): \Illuminate\Http\JsonResponse
    {
        $detailCategory->delete();

        if ($detailCategory){
            return response()->json([
                'message' => 'Detail Category deleted successfully',
            ]);
        } else {
            return response()->json([
                'message' => 'Detail Category not deleted',
                'error' => $detailCategory->errors(),
            ]);
        }
    }
}
