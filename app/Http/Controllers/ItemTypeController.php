<?php

namespace App\Http\Controllers;

use App\Models\ItemType;
use Illuminate\Http\Request;

class ItemTypeController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $itemTypes = ItemType::all();
        return response()->json($itemTypes);
    }

    public function show(ItemType $itemType): \Illuminate\Http\JsonResponse
    {
        return response()->json($itemType);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required',
        ]);

        $itemType = ItemType::create([
            'name' => $request->name,
        ]);

        if ($itemType) {
            return response()->json([
                'message' => 'Item Type created successfully',
                'itemType' => $itemType,
            ]);
        } else {
            return response()->json([
                'message' => 'Item Type not created',
                'error' => $itemType->errors(),
            ]);
        }
    }

    public function update(Request $request, ItemType $itemType): \Illuminate\Http\JsonResponse
    {
        $itemType->update($request->all());
        return response()->json([
            'message' => 'Item Type updated successfully',
            'itemType' => $itemType,
        ]);
    }
}
