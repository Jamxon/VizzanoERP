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
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {

        $plannedTime = $request->input('planned_time') ?? now()->toDateString();

        $orders = OrderPrintingTimes::where('status', 'printing')
            ->whereDate('planned_time', $plannedTime)
            ->orderBy('planned_time', 'asc')
            ->with('orderModel.model', 'orderModel.submodels', 'orderModel.submodels.size', 'orderModel.submodels.modelColor')
            ->get();

        $orders->each(function ($order) {
            $orderModel = $order->orderModel;  // orderModel bitta model bo'lgani uchun loop shart emas
            if ($orderModel) {
                $orderModel->model->makeHidden(['submodels']);
                $orderModel->submodels->each(function ($submodel) {
                    $submodel->submodel->makeHidden(['sizes', 'modelColors']);
                });
            }
        });


        return response()->json($orders);

    }


}