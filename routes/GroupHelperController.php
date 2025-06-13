<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\GetOrderGroupMasterResource;
use App\Models\Order;
use App\Models\OrderGroup;
use Illuminate\Http\Request;

class GroupHelperController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (!$user->employee->group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $query = OrderGroup::where('group_id', $user->employee->group->id)
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['pending', 'tailoring']);
            })
            ->with([
                'order.orderModel',
                'order.orderModel.model',
                'order.orderModel.material',
                'order.orderModel.sizes.size',
                'order.instructions',
            ])
            ->selectRaw('DISTINCT ON (order_id, submodel_id) *');

        $orders = $query->get();

        $orders = $orders->groupBy('order_id')->map(function ($orderGroups) {
            $firstOrderGroup = $orderGroups->first();
            $order = $firstOrderGroup->order;

            if ($order && $order->orderModel) {
                $linkedSubmodelIds = $orderGroups->pluck('submodel_id')->unique();

                $order->orderModel->submodels = $order->orderModel->submodels
                    ->whereIn('id', $linkedSubmodelIds)
                    ->values();

                // Tikilgan umumiy sonni hisoblash
                $totalSewn = $order->orderModel->submodels->flatMap(function ($submodel) {
                    return $submodel->sewingOutputs;
                })->sum('quantity');

                // Dinamik property qo‘shish yoki resource ichida ishlatish
                $order->total_sewn_quantity = $totalSewn;
            }

            return $firstOrderGroup;
        })->values();


        return response()->json(GetOrderGroupMasterResource::collection($orders));
    }
}