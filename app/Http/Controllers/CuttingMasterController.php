<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderModel;
use App\Models\OrderPrintingTimes;
use App\Models\Outcome;
use App\Models\OutcomeItemModelDistrubition;
use App\Models\ProductionOutcome;
use Illuminate\Http\Request;

class CuttingMasterController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $status = $request->input('status');
        $orders = Order::where('status', $status)
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->whereDate('start_date', '<=', now()->addDays(15)->toDateString())
            ->orderBy('start_date', 'asc')
            ->with(
                'instructions',
                'orderModel.model',
                'orderModel.submodels',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size',
                'orderPrintingTime'
            )
            ->get();

        return response()->json($orders);
    }

    public function sendToConstructor(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'planned_time' => 'required|date',
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
        $orders = Order::where('branch_id', auth()->user()->employee->branch_id)
            ->whereDate('start_date', '<=', now()->addDays(15)->toDateString())
            ->get();

        $orderModelIds = $orders->pluck('orderModel')->flatten()->pluck('id')->toArray();

        $outcomeItemModelDistribution = OutcomeItemModelDistrubition::whereIn('model_id', $orderModelIds)
            ->whareHas('outcomeItem', function ($query) {
                $query->whereHas('outcome', function ($query) {
                    $query->where('outcome_type', 'production');
                    $query->whareHas('productionOutcome', function ($query) {
                        $query->where('received_by_id', auth()->user()->id);
                    });
                });
            })
            ->with(
                'outcomeItem.outcome.items.product',
                'orderModel',
                'orderModel.model',
                'orderModel.order'
            )
            ->get();


        return response()->json($outcomeItemModelDistribution);
    }

    public function acceptCompletedItem($id): \Illuminate\Http\JsonResponse
    {
        $outcome = Outcome::find($id);

        $outcome->update([
            'status' => 'completed'
        ]);

        return response()->json($outcome);
    }

    public function cancelCompletedItem($id): \Illuminate\Http\JsonResponse
    {
        $outcome = Outcome::find($id);

        $outcome->update([
            'status' => 'cancelled'
        ]);

        return response()->json($outcome);
    }
}
