<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderModel;
use App\Models\OrderPrintingTimes;
use App\Models\Outcome;
use App\Models\ProductionOutcome;
use Illuminate\Http\Request;

class CuttingMasterController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('status', 'active')
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->whereDate('start_date', '<=', now()->addDays(15)->toDateString())
            ->orderBy('start_date', 'asc')
            ->with('instructions','orderModel.model', 'orderModel.submodels', 'orderModel.submodels.submodel','orderModel.sizes.size')
            ->get();

        return response()->json($orders);
    }

    public function sendToConstructor(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'planned_time' => 'required|dateTime',
            'comment' => 'nullable|string'
        ]);

        $order = Order::find($data['order_id']);

        $order->update([
            'status' => 'printing'
        ]);

        $orderPrintingTime = OrderPrintingTimes::create([
            'order_id' => $data['order_id'],
            'planned_time' => $data['planned_time'],
            'status' => 'printing',
            'comment' => $data['comment'],
            'user_id' => auth()->user()->id
        ]);

        return response()->json($orderPrintingTime);
    }

    public function getCompletedItems(): \Illuminate\Http\JsonResponse
    {
        $items = ProductionOutcome::where('received_by_id', auth()->user()->id)
            ->whereHas('outcome', function ($query) {
                $query->where('outcome_type', 'production');
            })
            ->with('outcome.items.product')
            ->get();

        return response()->json($items);
    }

    public function acceptCompletedItem($id)
    {
        $outcome = Outcome::find($id);

        $outcome->update([
            'status' => 'completed'
        ]);

        return response()->json($outcome);
    }

    public function cancelCompletedItem($id)
    {
        $outcome = Outcome::find($id);

        $outcome->update([
            'status' => 'cancelled'
        ]);

        return response()->json($outcome);
    }
}
