<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderPrintingTime;
use App\Models\Order;
use App\Models\OrderModel;
use App\Models\OrderPrintingTimes;
use Illuminate\Http\Request;

class ConstructorController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {

        $plannedTime = $request->input('planned_time') ?? now()->toDateString();
        $orders = Order::whereHas('orderModels.orderPrintingTimes', function ($query) use ($plannedTime) {
                $query->whereDate('planned_time', $plannedTime);
            })
            ->with('orderModels.orderPrintingTimes')
            ->get();

        $resource = OrderPrintingTime::collection($orders);

        return response()->json($resource);
    }

    public function sendToCuttingMaster($id): \Illuminate\Http\JsonResponse
    {
        $orderPrintingTime = OrderPrintingTimes::find($id);

        $orderModel = OrderModel::find($orderPrintingTime->order_model_id);

        $order = Order::find($orderModel->order_id);

        $order->update([
            'status' => 'cutting'
        ]);

        $orderPrintingTime->update([
            'status' => 'cutting',
            'actual_time' => now(),
            'user_id' => auth()->user()->id
        ]);

        return response()->json($orderPrintingTime);
    }

}