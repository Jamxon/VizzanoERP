<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Log;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Bonus;

class PackageMasterController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = $request->input('search', '');

        $orders = Order::where('branch_id', auth()->user()->employee->branch_id)
            ->whereIn('status', ['tailoring', 'tailored', 'checking', 'checked'])
            ->where(function ($query) use ($search) {
                $query->whereHas('orderModel.model', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            })
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

            // 4.1. Bonus qo‘shish
            $employees = Employee::where('payment_type', 'fixed_packaged_bonus')
                ->where('status', '!=', 'kicked')
                ->where('branch_id', $order->branch_id)
                ->get();

            $minutes = $order->orderModel->rasxod / 250;
            $totalPackagedItems = $newTotal;

            foreach ($employees as $employee) {
                $bonusAmount = $employee->bonus * $minutes * $totalPackagedItems;
                $oldBalance = $employee->balance;
                $employee->balance += $bonusAmount;
                $employee->save();

                // 🔹 Bonus jadvaliga yozamiz
                Bonus::create([
                    'employee_id' => $employee->id,
                    'order_id' => $order->id,
                    'type' => 'fixed_packaged_bonus',
                    'amount' => $bonusAmount,
                    'quantity' => $totalPackagedItems,
                    'old_balance' => $oldBalance,
                    'new_balance' => $employee->balance,
                    'created_by' => auth()->id(),
                ]);

                // 🔸 Log yozish
                Log::add(
                    auth()->id(),
                    'Qadoqlovchiga bonus qo‘shildi',
                    'packaging_bonus',
                    $oldBalance,
                    $employee->balance,
                    request()->ip(),
                    request()->userAgent()
                );
            }

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
