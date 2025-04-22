<?php

namespace App\Http\Controllers;

use App\Models\Contragent;
use App\Models\Destination;
use App\Models\Order;
use App\Models\Source;
use App\Models\StockBalance;
use App\Models\StockEntry;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as LaravelLog;
use App\Models\Log;

class WarehouseController extends Controller
{
    public function getWarehouses(): \Illuminate\Http\JsonResponse
    {
        $warehouses = Warehouse::where('branch_id', auth()->user()->employee->branch_id)->get();

        return response()->json($warehouses);
    }

    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('branch_id', auth()->user()->employee->branch_id)
            ->with([
                'contragent'
            ])
            ->get();

        return response()->json($orders);
    }

    public function getContragents(): \Illuminate\Http\JsonResponse
    {
        $contragents = Contragent::where('branch_id', auth()->user()->employee->branch_id)
            ->get();

        return response()->json($contragents);
    }

    public function getIncoming(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = $request->only(['source_id', 'warehouse_id', 'order_id', 'user_id', 'search']);

        $incoming = StockEntry::query()
            ->where('type', 'incoming')
            ->when($filters['source_id'] ?? null, fn($q, $v) => $q->where('source_id', $v))
            ->when($filters['warehouse_id'] ?? null, fn($q, $v) => $q->where('warehouse_id', $v))
            ->when($filters['order_id'] ?? null, fn($q, $v) => $q->where('order_id', $v))
            ->when($filters['user_id'] ?? null, fn($q, $v) => $q->where('user_id', $v))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('comment', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
            })
            ->with([
                'items.currency',
                'items.item',
                'warehouse',
                'source',
                'destination',
                'user',
                'order'
            ])
            ->latest('updated_at')
            ->paginate(10);

        return response()->json($incoming);
    }


    public function storeIncoming(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'source_id' => 'nullable|exists:sources,id',
            'source_name' => 'nullable|string',
            'comment' => 'nullable|string',
            'order_id' => 'nullable|exists:orders,id',
            'contragent_id' => 'nullable|exists:contragents,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.currency_id' => 'required|exists:currencies,id',
        ]);

        DB::beginTransaction();
        try {

            if (empty($validated['source_id']) && !empty($validated['source_name'])) {
                $source = Source::firstOrCreate(['name' => $validated['source_name']]);
                $validated['source_id'] = $source->id;
            }

            $entry = StockEntry::create([
                'type' => 'incoming',
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'source_id' => $validated['source_id'] ?? null,
                'destination_id' => null,
                'comment' => $validated['comment'] ?? null,
                'order_id' => $validated['order_id'] ?? null,
                'user_id' => auth()->id(),
                'contragent_id' => $validated['contragent_id'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $entry->items()->create([
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'] ?? null,
                    'currency_id' => $item['currency_id'] ?? null,
                ]);

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
                'items.currency',
                'items.item',
                'warehouse',
                'source',
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
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'destination_id' => 'nullable|exists:destinations,id',
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

            if (empty($validated['destination_id']) && !empty($validated['destination_name'])) {
                $destination = Destination::firstOrCreate(['name' => $validated['destination_name']]);
                $validated['destination_id'] = $destination->id;
            }

            $entry = StockEntry::create([
                'type' => 'outgoing',
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'destination_id' => $validated['destination_id'] ?? null,
                'source_id' => $validated['source_id'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'created_by' => auth()->id(),
                'order_id' => $validated['order_id'] ?? null,
                'user_id' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $entry->items()->create([
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'] ?? null,
                    'currency_id' => $item['currency_id'] ?? null,
                ]);

                $balance = StockBalance::firstOrCreate(
                    [
                        'item_id' => $item['item_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'order_id' => $validated['order_id'] ?? null,
                    ],
                    ['quantity' => 0]
                );

                $oldQty = $balance->quantity;
                $balance->quantity -= $item['quantity'];
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