<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetOrderGroupMasterResource;
use App\Http\Resources\GetTarificationGroupMasterResource;
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
            'order.orderModel',
            'order.orderModel.model',
            'order.orderModel.material',
            'order.orderModel.sizes.size',
            'order.orderModel.submodels.submodel',
            'order.orderModel.submodels.group',
            'order.instructions'
        ])->get();

        $resource = GetOrderGroupMasterResource::collection($orders);

        return response()->json($resource);
    }

    public function getEmployees(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (!$user->group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $employees = $user->group->employees()->get();

        return response()->json($employees);
    }

    public function getTarifications(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (!$user->group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $tarifications = $user->group->orders()->with([
            'order.orderModel.submodels.tarificationCategories',
            'order.orderModel.submodels.tarificationCategories.tarifications',
        ])->get();

        $resource = GetTarificationGroupMasterResource::collection($tarifications);

        return response()->json($resource);
    }
}
