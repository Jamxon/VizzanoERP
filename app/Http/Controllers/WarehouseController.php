<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Models\StockBalance;
use App\Models\StockEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as LaravelLog;
use App\Models\Log;

class WarehouseController extends Controller
{
    public function getIncoming(): \Illuminate\Http\JsonResponse
    {
        $incoming = StockEntry::where('type', 'incoming')
            ->with([
                'item',
                'warehouse',
                'source',
                'currency',
                'destination',
                'user',
                ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($incoming);
    }

    public function storeIncoming(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id', // umumiy ombor (ixtiyoriy)
            'source_id' => 'nullable|exists:sources,id',
            'comment' => 'nullable|string',
            'order_id' => 'nullable|exists:orders,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.currency_id' => 'nullable|exists:currencies,id',
        ]);

        DB::beginTransaction();
        try {
            $entry = StockEntry::create([
                'type' => 'incoming',
                'warehouse_id' => $validated['warehouse_id'] ?? null, // umumiy ombor (ixtiyoriy)
                'source_id' => $validated['source_id'] ?? null,
                'destination_id' => null,
                'comment' => $validated['comment'] ?? null,
                'created_by' => auth()->id(),
                'order_id' => $validated['order_id'] ?? null,
                'user_id' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                // Itemni entryga bog‘lash
                $entry->items()->create([
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'] ?? null,
                    'currency_id' => $item['currency_id'] ?? null,
                ]);

                // Zaxirani yangilash
                $balance = StockBalance::firstOrCreate(
                    [
                        'item_id' => $item['item_id'],
                        'warehouse_id' => $validated['warehouse_id'],
                        'order_id' => $validated['order_id'] ?? null,
                    ],
                    ['quantity' => 0]
                );

                $oldQty = $balance->quantity;
                $balance->quantity += $item['quantity'];
                $balance->save();

                Log::add(
                    auth()->id(),
                    'Omborga kirim qilindi',
                    'stock_in',
                    ['item_id' => $item['item_id'], 'quantity' => $oldQty],
                    ['item_id' => $item['item_id'], 'quantity' => $balance->quantity]
                );
            }

            DB::commit();
            return response()->json([
                'message' => 'Kirim muvaffaqiyatli amalga oshirildi',
                'entry' => $entry->load('items'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Xatolik', 'error' => $e->getMessage()], 500);
        }
    }

    public function getOutgoing(): \Illuminate\Http\JsonResponse
    {
        $outgoing = StockEntry::where('type', 'outgoing')
            ->with([
                'item',
                'warehouse',
                'source',
                'currency',
                'destination',
                'user',
                ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($outgoing);
    }

    public function storeOutgoing(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id', // umumiy ombor (ixtiyoriy)
            'destination_id' => 'nullable|exists:destinations,id', // destination (maqsad ombori)
            'source_id' => 'nullable|exists:sources,id',
            'comment' => 'nullable|string',
            'order_id' => 'nullable|exists:orders,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.currency_id' => 'nullable|exists:currencies,id',
        ]);

        DB::beginTransaction();
        try {
            $entry = StockEntry::create([
                'type' => 'outgoing',
                'warehouse_id' => $validated['warehouse_id'] ?? null, // umumiy ombor (ixtiyoriy)
                'destination_id' => $validated['destination_id'] ?? null,
                'source_id' => $validated['source_id'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'created_by' => auth()->id(),
                'order_id' => $validated['order_id'] ?? null,
                'user_id' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                // Itemni entryga bog‘lash
                $entry->items()->create([
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'] ?? null,
                    'currency_id' => $item['currency_id'] ?? null,
                ]);

                // Zaxirani yangilash
                $balance = StockBalance::firstOrCreate(
                    [
                        'item_id' => $item['item_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'order_id' => $validated['order_id'] ?? null,
                    ],
                    ['quantity' => 0]
                );

                $oldQty = $balance->quantity;
                $balance->quantity -= $item['quantity']; // Chiqim bo‘lgani uchun quantity minus
                $balance->save();

                Log::add(
                    auth()->id(),
                    'Ombordan chiqim amalga oshirildi',
                    'stock_out',
                    ['item_id' => $item['item_id'], 'quantity' => $oldQty],
                    ['item_id' => $item['item_id'], 'quantity' => $balance->quantity]
                );
            }

            DB::commit();
            return response()->json([
                'message' => 'Chiqim muvaffaqiyatli amalga oshirildi',
                'entry' => $entry->load('items'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Xatolik', 'error' => $e->getMessage()], 500);
        }
    }

    public function getStockBalances(Request $request): \Illuminate\Http\JsonResponse
    {
        $warehouseId = $request->input('warehouse_id');
        $orderId = $request->input('order_id');

        $query = StockBalance::with('item', 'warehouse');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($orderId) {
            $query->where('order_id', $orderId);
        }

        return response()->json($query->get());
    }
}