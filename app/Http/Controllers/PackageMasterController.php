<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageMasterController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('branch_id', auth()->user()->employee->branch_id)
            ->whereIn('status', ['tailoring', 'tailored', 'checking', 'checked'])
            ->with(
                'orderModel.model',
                'orderModel.material',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size'
            )
            ->get();

        return response()->json($orders);
    }

    public function showOrder(Order $order): \Illuminate\Http\JsonResponse
    {
            $order->load(
                'packageOutcomes',
                'orderModel.model',
                'orderModel.material',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size',
            );

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

            // 1. Jami hozirgacha qadoqlangan mahsulotlar
            $existingTotal = $order->packageOutcomes()
                ->selectRaw('SUM(package_size * package_quantity) as total')
                ->value('total') ?? 0;

            // 2. Yangi kirayotgan paket miqdori
            $newTotal = $request->input('package_size') * $request->input('package_quantity');

            $combinedTotal = $existingTotal + $newTotal;

            // 3. Tekshir: umumiy miqdordan oshmasligi kerak
            if ($combinedTotal > $order->quantity) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Umumiy miqdordan oshib ketmasligi kerak.'
                ], 422);
            }

            // 4. Paketni yozamiz
            $order->packageOutcomes()->create([
                'order_id' => $request->input('order_id'),
                'package_size' => $request->input('package_size'),
                'package_quantity' => $request->input('package_quantity'),
            ]);

            // 5. Order statusini yangilaymiz
            if ($combinedTotal === $order->quantity) {
                $order->status = 'completed';
            } elseif ($order->status === 'checked') {
                $order->status = 'packaging';
            }

            $order->save();

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
