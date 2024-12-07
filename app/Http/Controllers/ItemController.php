<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index()
    {
        $items = Item::orderBy('created_at', 'asc')->get();;
        return response()->json($items);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'price' => 'required',
            'unit_id' => 'required',
            'color_id' => 'required',
        ]);

        $item = Item::create([
            'name' => $request->name,
            'price' => $request->price,
            'unit_id' => $request->unit_id,
            'color_id' => $request->color_id,
            'image' => $request->image ?? null,
        ]);

        if ($item) {
            return response()->json([
                'message' => 'Item created successfully',
                'item' => $item,
            ]);
        } else {
            return response()->json([
                'message' => 'Item not created',
                'error' => $item->errors(),
            ]);
        }
    }

    public function update(Request $request, Item $item)
    {
        $item->update($request->all());
        return response()->json([
            'message' => 'Item updated successfully',
            'item' => $item,
        ]);
    }
}
