<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class ConstructorController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('status', 'active')
            ->whereDate('start_date', '<=', now()->addDays(3)->toDateString())
            ->orderBy('start_date', 'asc')
            ->with('orderModels.model', 'orderModels.submodels', 'orderModels.submodels.size', 'orderModels.submodels.modelColor')
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
}