<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Log;
use App\Models\SupplierOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:users,id',
            'comment' => 'nullable|string',
            'deadline' => 'required|date',
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
                'status' => 'new',
                'created_by' => auth()->id(),
                'deadline' => $request->deadline,
                'completed_date' => null,
                'received_date' => null,
                'branch_id' => auth()->user()->employee->branch_id,
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

    public function getSupplier(): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()?->employee?->branch_id;

        if (!$branchId) {
            return response()->json(['message' => 'Branch ID topilmadi.'], 400);
        }

        $suppliers = User::whereHas('role', function ($query) {
            $query->where('name', 'supplier');
        })
            ->whereHas('employee', function ($query) use ($branchId) {
                $query->where('status', '!=', 'kicked')
                    ->where('branch_id', $branchId);
            })
            ->with('employee')
            ->get();

        return response()->json($suppliers);
    }

    public function getSupplierOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $supplierOrders = SupplierOrder::where('branch_id', auth()->user()->employee->branch_id)
            ->with([
                'items.item',
                'items.item.unit',
                'items.item.color',
                'items.item.type',
                'items.item.currency',
                'supplier.employee'
            ])
            ->orderBy('deadline', 'desc')
            ->paginate(20);

        return response()->json($supplierOrders);
    }

}
