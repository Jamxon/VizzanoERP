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
    public function index(): \Illuminate\Http\JsonResponse
    {
        $items = Item::orderBy('updated_at', 'desc')
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->with('unit', 'color', 'type')
            ->paginate(10);
        return response()->json($items);
    }

    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = $request->input('search');
        $type = $request->input('type');
        $items = Item::where('branch_id', auth()->user()->employee->branch_id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%$query%")
                    ->orWhere('code', 'like', "%$query%");
            })
            ->when($type, function ($q) use ($type) {
                $q->where('type_id', $type);
            })
            ->with('unit', 'color', 'type')
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        return response()->json($items);
    }

    public function export(): \Illuminate\Http\JsonResponse
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
            'message' => 'Eksport jarayoni navbatga olindi',
            'fileUrl' => $fileUrl,
        ]);
    }

    public function show(Item $item): \Illuminate\Http\JsonResponse
    {
        $item->load(
            'unit',
            'color',
            'type',
            'stockBalances',
            'stockEntries',
        );
        return response()->json($item);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->input('data'), true);

        if (!$data) {
            return response()->json(['error' => 'Invalid JSON data'], 400);
        }

        $validated = $request->merge($data)->validate([
            'name' => 'required|string',
            'price' => 'required|numeric',
            'unit_id' => 'required|exists:units,id',
            'color_id' => 'required|exists:colors,id',
            'type_id' => 'required|exists:item_types,id',
            'code' => 'nullable|unique:items,code',
            'currency' => 'nullable|string',
        ], [
            'code.unique' => 'Code must be unique',
        ]);

        $imagePath = null;
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('/items', $imageName);
            $imagePath = str_replace('public/', '', $imagePath);
        }

        $item = Item::create([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'unit_id' => $validated['unit_id'],
            'color_id' => $validated['color_id'],
            'type_id' => $validated['type_id'],
            'code' => $validated['code'] ?? uniqid(),
            'image' => $imagePath,
            'branch_id' => auth()->user()->employee->branch_id,
            'currency' => $validated['currency'],
        ]);

        return response()->json([
            'message' => $item ? 'Item created successfully' : 'Item not created',
            'item' => $item,
        ], $item ? 201 : 500);
    }

    public function update(Request $request, Item $item): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->input('data'), true);

        if (!$data) {
            return response()->json(['error' => 'Invalid JSON data'], 400);
        }

        $validated = $request->merge($data)->validate([
            'name' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'unit_id' => 'sometimes|exists:units,id',
            'color_id' => 'sometimes|exists:colors,id',
            'type_id' => 'sometimes|exists:item_types,id',
            'code' => 'sometimes|unique:items,code,' . $item->id,
            'currency' => 'sometimes|string',
        ], [
            'code.unique' => 'Code must be unique',
        ]);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('/items', $imageName);
            $imagePath = str_replace('public/', '', $imagePath);

            // Eski rasmni o'chirish
            if ($item->image && Storage::exists('public/items' . $item->image)) {
                Storage::delete('public/' . $item->image);
            }

            $item->image = $imagePath;
        }

        $item->name = $validated['name'] ?? $item->name;
        $item->price = $validated['price'] ?? $item->price;
        $item->unit_id = $validated['unit_id'] ?? $item->unit_id;
        $item->color_id = $validated['color_id'] ?? $item->color_id;
        $item->type_id = $validated['type_id'] ?? $item->type_id;
        $item->code = $validated['code'] ?? $item->code;
        $item->branch_id = auth()->user()->employee->branch_id;
        $item->currency = $validated['currency'] ?? $item->currency;
        $item->save();

        return response()->json([
            'message' => 'Item updated successfully',
            'item' => $item,
        ]);
    }

}