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
            ->get();

        $orders->each(function ($order) {
            $order->orderModel->each(function ($orderModel) {
                $orderModel->model->makeHidden(['submodels']); // 'model' dan 'submodels'ni yashiradi
                $orderModel->submodels->each(function ($submodel) {
                    $submodel->submodel->makeHidden(['sizes', 'modelColors']); // 'submodel' dan 'sizes' va 'modelColors'ni yashiradi
                });
            });
        }

        return response()->json($orders);

    }


}