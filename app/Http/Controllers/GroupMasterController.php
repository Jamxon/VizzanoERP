<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GroupMasterController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (!$user->group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $orders = $user->group->orders()->with([
            'orderModel',
            'orderModel.model',
            'orderModel.material',
            'orderModel.sizes.size',
            'orderModel.submodels.submodel',
            'orderModel.submodels.group',
            'instructions'
        ])->get();

        return response()->json($orders);
    }

}
