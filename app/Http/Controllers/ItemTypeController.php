<?php

namespace App\Http\Controllers;

use App\Models\ItemType;
use Illuminate\Http\Request;

class ItemTypeController extends Controller
{
    public function index()
    {
        $itemTypes = ItemType::all();
        return response()->json($itemTypes);
    }

    public function show(ItemType $itemType)
    {
        return response()->json($itemType);
    }

    public function store(Request $request)
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

    public function update(Request $request, ItemType $itemType)
    {
        $itemType->update($request->all());
        return response()->json([
            'message' => 'Item Type updated successfully',
            'itemType' => $itemType,
        ]);
    }

    public function destroy(ItemType $itemType)
    {
        $itemType->delete();
        return response()->json([
            'message' => 'Item Type deleted successfully',
            'itemType' => $itemType,
        ]);
    }
}
