<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class InternalAccountantController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('branch_id', auth()->user()->employee->branch_id)
            ->whereIn('status', ['tailoring', 'tailored', 'checking', 'checked', 'packaging', 'completed'])
            ->with(
                'orderModel',
                'orderModel.model',
                'orderModel.materials',
                'orderModel.submodels.submodel',
                'orderModel.submodels.tarificationCategories',
                'orderModel.submodels.tarificationCategories.tarifications',
                'orderModel.submodels.tarificationCategories.tarifications.employee',
                'orderModel.submodels.tarificationCategories.tarifications.razryad',
                'orderModel.submodels.tarificationCategories.tarifications.typewriter',
            )
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($orders);
    }
}
