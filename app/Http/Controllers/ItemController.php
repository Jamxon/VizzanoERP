<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyUserOfCompletedExport;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockEntryItem;
use Illuminate\Http\Request;
use App\Exports\ItemsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = strtolower($request->get('search'));
        $type = $request->input('type_id');

        $items = Item::where('branch_id', auth()->user()->employee->branch_id)
            ->where(function ($q) use ($query) {
                $q->orWhereRaw('LOWER(name) LIKE ?', ["%$query%"])
                    ->orWhereRaw('LOWER(code) LIKE ?', ["%$query%"]);
            })
            ->when($type, fn($q) => $q->where('type_id', $type))
            ->with('unit', 'color', 'type', 'currency')
            ->orderBy('updated_at', 'desc')
            ->paginate(50);

        return response()->json($items);
    }

    public function search(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = strtolower($request->get('search'));
        $type = $request->input('type_id');
        $items = Item::where('branch_id', auth()->user()->employee->branch_id)
            ->where(function ($q) use ($query) {
                $q->orWhereRaw('LOWER(name) LIKE ?', ["%$query%"])
                    ->orWhereRaw('LOWER(code) LIKE ?', ["%$query%"]);
            })
            ->when($type, function ($q) use ($type) {
                $q->where('type_id', $type);
            })
            ->with('unit', 'color', 'type','currency')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($items);
    }

    public function export(): \Illuminate\Http\JsonResponse
    {
        $filePath = storage_path('app/public/materiallar.xlsx');
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        Excel::queue(new ItemsExport, 'materiallar.xlsx')->chain([
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
        try {
            $branchId = auth()->user()?->employee?->branch_id;

            $item->load(['unit', 'color', 'type', 'currency']);

            // Shu itemga tegishli barcha stock_balancelarni branch bo‘yicha olish
            $balances = StockBalance::where('item_id', $item->id)
                ->whereHas('warehouse', fn($q) => $q->where('branch_id', $branchId))
                ->with(
                    'order',
                    'warehouse',
                )
                ->get(['id', 'quantity', 'warehouse_id', 'order_id']);

            // Shu itemga tegishli kirim/chiqim tarixini olish
            $entryItems = StockEntryItem::where('item_id', $item->id)
                ->whereHas('stockEntry', function ($query) use ($branchId) {
                    $query->whereHas('warehouse', fn($q) => $q->where('branch_id', $branchId));
                })
                ->with([
                    'stockEntry' => function ($q) {
                        $q->with([
                            'employee:id,user_id,id,name',
                            'source',
                            'destination',
                            'responsibleUser',
                            'warehouse'
                        ]);
                    }
                ])
                ->orderByDesc('id')
                ->get();

            // Kirim/chiqim tarixini formatlash
            $history = $entryItems->map(function ($entryItem) {
                $entry = $entryItem->stockEntry;

                return [
                    'id' => $entry->id,
                    'type' => $entry->type,
                    'created_at' => $entry->created_at,
                    'comment' => $entry->comment,
                    'employee' => [
                        'id' => $entry->employee?->id,
                        'name' => $entry->employee?->name,
                    ],
                    'quantity' => $entryItem->quantity,
                    'price' => $entryItem->price,
                    'source' => $entry->source ?? null,
                    'responsibleUser' => $entry->responsibleUser,
                    'destination' => $entry->destination,
                    'warehouse' => $entry->warehouse,
                ];
            });

            return response()->json([
                'item' => $item,
                'stock_balances' => $balances,
                'history' => $history,
            ]);
        } catch (\Throwable $e) {

            return response()->json([
                'message' => 'Xatolik yuz berdi. Administrator bilan bog‘laning.',
                'error' => $e->getMessage()
            ], 500);
        }
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
            'code' => 'nullable',
            'currency_id' => 'nullable|integer',
            'min_quantity' => 'nullable|numeric',
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
            'currency_id' => $validated['currency_id'],
            'min_quantity' => $validated['min_quantity'] ?? 0,
            'lot' => $validated['lot'] !== '' ? $validated['lot'] : null,
        ]);

        return response()->json([
            'message' => $item ? 'Item created successfully' : 'Item not created',
            'item' => $item,
        ], $item ? 201 : 500);
    }

    public function update(Request $request, Item $item): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string',
                'price' => 'sometimes|numeric',
                'unit_id' => 'sometimes|exists:units,id',
                'color_id' => 'sometimes|exists:colors,id',
                'type_id' => 'sometimes|exists:item_types,id',
                'code' => 'sometimes',
                'currency_id' => 'sometimes|integer',
                'min_quantity' => 'sometimes|numeric',
                'lot' => 'sometimes',
            ]);

            // 1. Rasm yangilanishi
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $image = $request->file('image');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $imagePath = $image->storeAs('/items', $imageName);
                $imagePath = str_replace('public/', '', $imagePath);

                // eski rasmni o'chirish
                if ($item->image && Storage::exists('public/' . $item->image)) {
                    Storage::delete('public/' . $item->image);
                }

                $item->image = $imagePath;
            }

            // 2. image = null deb kelsa
            elseif ($request->has('image') && in_array($request->input('image'), [null, 'null', ''])) {
                if ($item->image && Storage::exists('public/' . $item->image)) {
                    Storage::delete('public/' . $item->image);
                }

                $item->image = null;
            }

            // 3. Qolgan ma'lumotlar
            $item->name = $validated['name'] ?? $item->name;
            $item->price = $validated['price'] ?? $item->price;
            $item->unit_id = $validated['unit_id'] ?? $item->unit_id;
            $item->color_id = $validated['color_id'] ?? $item->color_id;
            $item->type_id = $validated['type_id'] ?? $item->type_id;
            $item->code = $validated['code'] ?? $item->code;
            $item->currency_id = $validated['currency_id'] ?? $item->currency_id;
            $item->min_quantity = $validated['min_quantity'] ?? $item->min_quantity;
            $item->lot = $validated['lot'] !== '' ? $validated['lot'] : null;
            $item->branch_id = auth()->user()->employee->branch_id;

            $item->save();

            return response()->json([
                'message' => 'Item updated successfully',
                'item' => $item,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Xatolik yuz berdi!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}