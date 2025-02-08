<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class TailorMasterController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('branch_id' , auth()->user()->branch_id)
            ->where('status', 'printing')
            ->where('status', 'cutting')
            ->where('status', 'tailoring')
            ->with(
                'orderModel',
                'orderModel.model',
                'orderModel.material',
                'orderModel.sizes.size',
                'orderModel.submodels.submodel',
                'instructions'
            )
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    }
}