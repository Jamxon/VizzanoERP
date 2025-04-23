<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Log;
use App\Models\SupplierOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:users,id',
            'comment' => 'nullable|string',
            'deadline' => 'required|datetime',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            // Kod yaratish: ORD-0001
            $lastId = SupplierOrder::max('id') + 1;
            $code = 'ORD-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

            $order = SupplierOrder::create([
                'supplier_id' => $request->supplier_id,
                'code' => $code,
                'comment' => $request->comment,
                'status' => 'pending',
                'created_by' => auth()->id(),
                'deadline' => $request->deadline->format('Y-m-d H:i:s'),
                'completed_date' => null
            ]);

            foreach ($request->items as $item) {
                $items = Item::findOrFail($item['item_id']);

                $order->items()->create([
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'price' => $items->price,
                    'currency_id' => $items->currency_id,
                ]);
            }

            DB::commit();

            Log::add(
                auth()->user()->id,
                "Ta'minotchiga buyurtma berildi ($code)",
                'create',
                null,
                $order->load('supplier', 'items.item')
            );

            return response()->json([
                'message' => 'Buyurtma muvaffaqiyatli yaratildi.',
                'order_id' => $order->id,
                'code' => $order->code
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Buyurtma yaratishda xatolik yuz berdi.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
