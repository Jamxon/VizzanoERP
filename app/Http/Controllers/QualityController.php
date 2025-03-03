<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class QualityController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('status' , $request->status)
            ->orderBy('updated_at', 'desc')
            ->with(
                'orderModel',
                'orderModel.model',
                'orderModel.sizes.size',
                'orderModel.material',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group.group',
            )
            ->get();

        return response()->json($orders);
    }
}
