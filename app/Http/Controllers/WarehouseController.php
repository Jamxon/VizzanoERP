<?php

namespace App\Http\Controllers;

use App\Models\StockBalance;
use App\Models\StockEntry;
use Illuminate\Http\Request;
use App\Models\Log;

class WarehouseController extends Controller
{
    public function getIncoming(): \Illuminate\Http\JsonResponse
    {
        $incoming = StockEntry::where('type', 'incoming')
            ->with(['item', 'warehouse'])
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
            'source' => 'required|string',
            'comment' => 'nullable|string',
            'order_id' => 'nullable|exists:orders,id',
            'price' => 'nullable|numeric',
            'currency' => 'nullable|string',
        ]);

        $entry = StockEntry::create([
            'item_id' => $validated['item_id'],
            'warehouse_id' => $validated['warehouse_id'],
            'quantity' => $validated['quantity'],
            'type' => 'incoming',
            'source' => $validated['source'],
            'destination' => null,
            'comment' => $validated['comment'],
            'created_by' => auth()->id(),
            'order_id' => $validated['order_id'],
            'price' => $validated['price'],
            'currency' => $validated['currency'],
        ]);

        $balance = StockBalance::firstOrCreate([
            'item_id' => $validated['item_id'],
            'warehouse_id' => $validated['warehouse_id'],
        ]);
        $oldQty = $balance->quantity;
        $balance->quantity += $validated['quantity'];
        $balance->save();

        Log::add(
            auth()->user()->id,
            'Kirim qo‘shildi',
            'stock_in',
            ['quantity' => $oldQty],
            ['quantity' => $balance->quantity]
        );

        return response()->json(['message' => 'Kirim muvaffaqiyatli qo‘shildi', 'data' => $entry]);
    }

    public function getOutgoing(): \Illuminate\Http\JsonResponse
    {
        $outgoing = StockEntry::where('type', 'outgoing')
            ->with(['item', 'warehouse'])
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
            'destination' => 'required|string',
            'comment' => 'nullable|string',
            'order_id' => 'nullable|exists:orders,id',
            'price' => 'nullable|numeric',
            'currency' => 'nullable|string',
        ]);

        $balance = StockBalance::where('item_id', $validated['item_id'])
            ->where('warehouse_id', $validated['warehouse_id'])
            ->first();

        if (!$balance || $balance->quantity < $validated['quantity']) {
            return response()->json(['message' => 'Zaxirada yetarli mahsulot mavjud emas'], 400);
        }

        $entry = StockEntry::create([
            'item_id' => $validated['item_id'],
            'warehouse_id' => $validated['warehouse_id'],
            'quantity' => $validated['quantity'],
            'type' => 'outgoing',
            'source' => null,
            'destination' => $validated['destination'],
            'comment' => $validated['comment'],
            'created_by' => auth()->id(),
            'order_id' => $validated['order_id'],
            'price' => $validated['price'],
            'currency' => $validated['currency'],
        ]);

        $oldQty = $balance->quantity;
        $balance->quantity -= $validated['quantity'];
        $balance->save();

        Log::add(
            auth()->user()->id,
            'Chiqim qo‘shildi',
            'stock_out',
            ['quantity' => $oldQty],
            ['quantity' => $balance->quantity]
        );

        return response()->json(['message' => 'Chiqim muvaffaqiyatli amalga oshirildi', 'data' => $entry]);
    }

    public function getStockBalances(Request $request): \Illuminate\Http\JsonResponse
    {
        $warehouseId = $request->input('warehouse_id');

        $query = StockBalance::with('item', 'warehouse');
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return response()->json($query->get());
    }
}
