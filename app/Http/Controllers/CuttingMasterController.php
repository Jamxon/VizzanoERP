<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderModel;
use App\Models\OrderPrintingTimes;
use Illuminate\Http\Request;

class CuttingMasterController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('status', 'active')
            ->whereDate('start_date', '<=', now()->addDays(15)->toDateString())
            ->orderBy('start_date', 'asc')
            ->with('orderModel.model', 'orderModel.submodels', 'orderModel.submodels.submodel')
            ->get();

        $orders->each(function ($order) {
            $order->orderModels->each(function ($orderModel) {
                $orderModel->model->makeHidden(['submodels']); // 'model' dan 'submodels'ni yashiradi
                $orderModel->submodels->each(function ($submodel) {
                    $submodel->submodel->makeHidden(['sizes', 'modelColors']); // 'submodel' dan 'sizes' va 'modelColors'ni yashiradi
                });
            });
        });

        return response()->json($orders);
    }

    public function sendToConstructor(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'order_model_id' => 'required|integer|exists:order_models,id',
            'planned_time' => 'required|date',
            'comment' => 'nullable|string'
        ]);
        $orderModel = OrderModel::find($data['order_model_id']);

        $order = Order::find($orderModel->order_id);

        $order->update([
            'status' => 'printing'
        ]);

        $orderPrintingTime = OrderPrintingTimes::create([
            'order_model_id' => $data['order_model_id'],
            'planned_time' => $data['planned_time'],
            'status' => 'printing',
            'comment' => $data['comment'],
            'user_id' => auth()->user()->id
        ]);

        return response()->json($orderPrintingTime);
    }
}
