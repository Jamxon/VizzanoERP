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
        $items = Item::orderBy('updated_at', 'desc')
            ->with('unit', 'color', 'type')
            ->get();
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
        // JSON ma'lumotlarni dekodlash
        $data = json_decode($request->input('data'), true);

        if (!$data) {
            return response()->json(['error' => 'Invalid JSON data'], 400);
        }

        // Validatsiya
        $validated = $request->merge($data)->validate([
            'name' => 'required|string',
            'price' => 'required|numeric',
            'unit_id' => 'required|exists:units,id',
            'color_id' => 'required|exists:colors,id',
            'type_id' => 'required|exists:item_types,id',
            'code' => 'nullable|unique:items,code',
        ], [
            'code.unique' => 'Code must be unique',
        ]);

        // Rasmni yuklash
        $imagePath = null;
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('public/images', $imageName);
            $imagePath = str_replace('public/', '', $imagePath);
        }

        // Ma'lumotni bazaga yozish
        $item = Item::create([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'unit_id' => $validated['unit_id'],
            'color_id' => $validated['color_id'],
            'type_id' => $validated['type_id'],
            'code' => $validated['code'] ?? uniqid(),
            'image' => $imagePath,
        ]);

        return response()->json([
            'message' => $item ? 'Item created successfully' : 'Item not created',
            'item' => $item,
        ], $item ? 201 : 500);
    }



    public function update(Request $request, Item $item)
    {
        // JSON ma'lumotlarni dekodlash
        $data = json_decode($request->input('data'), true);

        if (!$data) {
            return response()->json(['error' => 'Invalid JSON data'], 400);
        }

        // Validatsiya
        $validated = $request->merge($data)->validate([
            'name' => 'required|string',
            'price' => 'required|numeric',
            'unit_id' => 'required|exists:units,id',
            'color_id' => 'required|exists:colors,id',
            'type_id' => 'required|exists:item_types,id',
            'code' => 'nullable|unique:items,code,' . $item->id,
        ], [
            'code.unique' => 'Code must be unique',
        ]);

        // Rasmni yangilash
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('public/images', $imageName);
            $imagePath = str_replace('public/', '', $imagePath);

            // Eski rasmni o'chirish
            if ($item->image && Storage::exists('public/' . $item->image)) {
                Storage::delete('public/' . $item->image);
            }

            $item->image = $imagePath;
        }

        // Ma'lumotni yangilash
        $item->update([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'unit_id' => $validated['unit_id'],
            'color_id' => $validated['color_id'],
            'type_id' => $validated['type_id'],
            'code' => $validated['code'] ?? $item->code,
        ]);

        return response()->json([
            'message' => 'Item updated successfully',
            'item' => $item,
        ]);
    }

}