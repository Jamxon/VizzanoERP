<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyUserOfCompletedExport;
use App\Models\Item;
use Illuminate\Http\Request;
use App\Exports\ItemsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;


class ItemController extends Controller
{
    public function index()
    {
        $items = Item::orderBy('updated_at', 'desc')->get();
        return response()->json($items);
    }

    public function export()
    {
        $filePath = storage_path('app/public/materiallar.xlsx');
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        Excel::queue(new ItemsExport, 'public/materiallar.xlsx')->chain([
            new NotifyUserOfCompletedExport(auth()->user())
        ]);

        $fileUrl = url('storage/materiallar.xlsx');

        return response()->json([
            'message' => 'Eksport jarayoni navbatga bodybuilder.',
            'fileUrl' => $fileUrl,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'price' => 'required',
            'unit_id' => 'required|exists:units,id',
            'color_id' => 'required|exists:colors,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'type_id' => 'required|exists:item_types,id',
        ]);
        $request->validate([
            'code' => 'unique:items,code',
        ], [
            'code.unique' => 'Code must be unique',
        ]);

//        $imagePath = null;

        if ($request->hasFile('image') && $request->file('image')->isValid()) {

            $image = $request->file('image');

            $imageName = time() . '_' . $image->getClientOriginalName();

            $imagePath = $image->storeAs('public/images', $imageName);

//            $imageUrl = Storage::url($imagePath);

            $imagePath = str_replace('public/', '', $imagePath);
        } else {
            return response()->json(['error' => 'Image file is missing or invalid'], 400);
        }

        //$imageOriginalName = preg_split('/', $imageUrl)[2] ?? null;

        $item = Item::create([
            'name' => $request->name,
            'price' => $request->price  ?? 0,
            'unit_id' => $request->unit_id,
            'color_id' => $request->color_id,
            'image' => $imagePath,
            'code' => $request->code ?? uniqid(),
            'type_id' => $request->type_id,
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
        $request->validate([
            'name' => 'required',
            'price' => 'required',
            'unit_id' => 'required|exists:units,id',
            'color_id' => 'required|exists:colors,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'type_id' => 'required|exists:item_types,id',
            'code' => 'unique:items,code,' . $item->id,
        ], [
            'code.unique' => 'Code must be unique',
        ]);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('public/images', $imageName);
            $imagePath = str_replace('public/', '', $imagePath);

            if ($item->image && Storage::exists('public/' . $item->image)) {
                Storage::delete('public/' . $item->image);
            }

            $item->image = $imagePath;
        }

        $item->update([
            'name' => $request->name,
            'price' => $request->price ?? $item->price,
            'unit_id' => $request->unit_id,
            'color_id' => $request->color_id,
            'code' => $request->code ?? $item->code,
            'type_id' => $request->type_id,
        ]);

        return response()->json([
            'message' => 'Item updated successfully',
            'item' => $item,
        ]);
    }
}