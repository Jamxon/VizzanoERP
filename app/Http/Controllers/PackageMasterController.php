<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class PackageMasterController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('status', 'tailoring')
            ->orWhere('status', 'tailored')
            ->orWhere('status', 'checking')
            ->orWhere('status', 'checked')
            ->where('branch_id', auth()->user()->branch_id)
            ->with(
                'orderModel.model',
                'orderModel.submodels.submodel',
            )
            ->get();

        return response()->json($orders);
    }
}
