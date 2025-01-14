<?php

namespace App\Http\Controllers;

use App\Models\ConstructorOrder;
use App\Models\Order;
use App\Models\OrderPrintingTimes;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConstructorController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = OrderPrintingTimes::where('status', 'printing')
            ->orderBy('planned_time', 'asc')
            ->with('orderModel.model', 'orderModel.submodels', 'orderModel.submodels.size', 'orderModel.submodels.modelColor')
            ->get();

        $orders->each(function ($order) {
            $order->orderModel->each(function ($orderModel) {
                $orderModel->model->makeHidden(['submodels']);
                $orderModel->submodels->each(function ($submodel) {
                    $submodel->submodel->makeHidden(['sizes', 'modelColors']);
                });
            });
        });

        return response()->json($orders);

    }


}