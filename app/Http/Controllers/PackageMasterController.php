<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PackageMasterController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('status', 'tailoring')
            ->orWhere('status', 'tailored')
            ->orWhere('status', 'checking')
            ->orWhere('status', 'checked')
            ->where('branch_id', auth()->user()->branch_id)
            ->with(
                'orderModel.model',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size',
            )
            ->get();

        return response()->json($orders);
    }

    public function showOrder($id): \Illuminate\Http\JsonResponse
    {

         dd($order = Order::find($id)
            ->where('branch_id', auth()->user()->branch_id)
            ->with(
                'packageOutcome',
                'orderModel.model.material',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size',
            )
            ->first());

        return response()->json($order);
    }

    public function packageStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'package_size' => 'required|integer|min:1',
            'package_quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($request->input('order_id'));

            if ($order->status === 'checked') {
                $order->status = 'packaging';
                $order->save();
            }

            $order->packageOutcome()->create([
                'order_id' => $request->input('order_id'),
                'package_size' => $request->input('package_size'),
                'package_quantity' => $request->input('package_quantity'),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Paketlar muvaffaqiyatli yaratildi'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Xatolik yuz berdi: ' . $e->getMessage()
            ], 500);
        }
    }

}
