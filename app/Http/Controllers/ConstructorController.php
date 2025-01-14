<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderModel;
use App\Models\OrderPrintingTimes;
use Illuminate\Http\Request;

class ConstructorController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {

        $plannedTime = $request->input('planned_time') ?? now()->toDateString();

        $orders = OrderPrintingTimes::where('status', 'printing')
            ->whereDate('planned_time', $plannedTime)
            ->orderBy('planned_time', 'asc')
            ->with('orderModel.model', 'orderModel.submodels', 'orderModel.submodels.size', 'orderModel.submodels.modelColor')
            ->get();

        $orders->each(function ($order) {
            $orderModel = $order->orderModel;
            if ($orderModel) {
                $orderModel->model->makeHidden(['submodels']);
                $orderModel->submodels->each(function ($submodel) {
                    $submodel->submodel->makeHidden(['sizes', 'modelColors']);
                });
            }
        });
        return response()->json($orders);
    }

    public function sendToCuttingMaster(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
           'order_printing_times_id' => 'required|integer|exists:order_printing_times,id',
        ]);

        $orderPrintingTime = OrderPrintingTimes::find($data['order_printing_times_id']);

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