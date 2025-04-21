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
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'source_id' => 'required|integer',
            'comment' => 'nullable|string',
            'order_id' => 'nullable|exists:orders,id',
            'price' => 'nullable|numeric',
            'currency_id' => 'nullable|integer',
        ]);

        try {
            $entry = DB::transaction(function () use ($validated) {
                $entry = StockEntry::create([
                    'item_id' => $validated['item_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'quantity' => $validated['quantity'],
                    'type' => 'incoming',
                    'source_id' => $validated['source_id'],
                    'destination_id' => null,
                    'comment' => $validated['comment'],
                    'created_by' => auth()->id(),
                    'order_id' => $validated['order_id'],
                    'price' => $validated['price'],
                    'currency_id' => $validated['currency_id'],
                    'user_id' => auth()->id(),
                ]);

                $balance = StockBalance::firstOrCreate([
                    'item_id' => $validated['item_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'order_id' => $validated['order_id'],
                ]);

                $oldQty = $balance->quantity;
                $balance->quantity += $validated['quantity'];
                $balance->save();

                Log::add(
                    auth()->id(),
                    'Kirim qo‘shildi',
                    'stock_in',
                    ['quantity' => $oldQty],
                    ['quantity' => $balance->quantity]
                );

                return $entry;
            });

            return response()->json(['message' => 'Kirim muvaffaqiyatli qo‘shildi', 'data' => $entry]);
        } catch (\Throwable $e) {
            LaravelLog::error('Kirim qo‘shishda xatolik: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return response()->json(['message' => 'Xatolik yuz berdi.'], 500);
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
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'destination_id' => 'nullable|integer',
            'destination_name' => 'nullable|string',
            'comment' => 'nullable|string',
            'order_id' => 'nullable|exists:orders,id',
            'price' => 'nullable|numeric',
            'currency_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
        ]);

        try {
            $entry = DB::transaction(function () use ($validated) {
                $balance = StockBalance::where('item_id', $validated['item_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->where('order_id', $validated['order_id'])
                    ->first();

                if (!$balance || $balance->quantity < $validated['quantity']) {
                    throw new \Exception('Zaxirada yetarli mahsulot mavjud emas');
                }

                if ($validated['destination_id'] === null && $validated['destination_name'] !== null) {
                    $destination = new Destination();
                    $destination->name = $validated['destination_name'];
                    $destination->save();
                    $validated['destination_id'] = $destination->id;
                }

                $entry = StockEntry::create([
                    'item_id' => $validated['item_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'quantity' => $validated['quantity'],
                    'type' => 'outgoing',
                    'source_id' => null,
                    'destination_id' => $validated['destination_id'],
                    'comment' => $validated['comment'],
                    'created_by' => auth()->id(),
                    'order_id' => $validated['order_id'],
                    'price' => $validated['price'],
                    'currency_id' => $validated['currency_id'],
                    'user_id' => $validated['user_id'] ?? auth()->id(),
                ]);

                $oldQty = $balance->quantity;
                $balance->quantity -= $validated['quantity'];
                $balance->save();

                Log::add(
                    auth()->id(),
                    'Chiqim qo‘shildi',
                    'stock_out',
                    ['quantity' => $oldQty],
                    ['quantity' => $balance->quantity]
                );

                return $entry;
            });

            return response()->json(['message' => 'Chiqim muvaffaqiyatli amalga oshirildi', 'data' => $entry]);
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'Zaxirada yetarli mahsulot mavjud emas') {
                return response()->json(['message' => $e->getMessage()], 400);
            }

            LaravelLog::error('Chiqimda xatolik: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return response()->json(['message' => 'Xatolik yuz berdi.'], 500);
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