<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\GetOrderGroupMasterResource;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderSubModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function receiveOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderId = $request->input('order_id');
        $submodelId = $request->input('submodel_id');

        try {
            DB::beginTransaction();

            $order = Order::find($orderId);

            if (!$order) {
                return response()->json(['message' => 'Order not found'], 404);
            }

            $orderSubmodel = OrderSubmodel::find($submodelId);

            if (!$orderSubmodel) {
                return response()->json(['message' => 'OrderSubmodel not found'], 404);
            }

            $allOrderSubmodels = OrderSubmodel::where('order_model_id', $orderSubmodel->order_model_id)->pluck('id')->toArray();

            $existingOrderGroup = OrderGroup::where('submodel_id', $submodelId)->first();

            $oldData = null;
            $newData = [
                'order_id' => $order->id,
                'group_id' => auth()->user()->employee->group->id,
                'submodel_id' => $submodelId
            ];

            if ($existingOrderGroup) {
                $oldData = $existingOrderGroup->toArray();
                $existingOrderGroup->update([
                    'group_id' => auth()->user()->employee->group->id,
                ]);
                $newData = $existingOrderGroup->fresh()->toArray();
            } else {
                OrderGroup::create([
                    'order_id' => $order->id,
                    'group_id' => auth()->user()->employee->group->id,
                    'submodel_id' => $submodelId,
                ]);
            }

            $linkedSubmodels = OrderGroup::whereIn('submodel_id', $allOrderSubmodels)->distinct('submodel_id')->count();

            $orderOldStatus = $order->status;

            if (count($allOrderSubmodels) > 0 && count($allOrderSubmodels) == $linkedSubmodels) {
                $order->update(['status' => 'tailoring']);

                // Log order status change separately
                if ($orderOldStatus !== 'tailoring') {
                    Log::add(
                        auth()->id(),
                        "Buyurtma statusi o'zgartirildi!",
                        'receive',
                        ['order_id' => $order->id, 'status' => $orderOldStatus],
                        ['order_id' => $order->id, 'status' => 'tailoring']
                    );
                }
            }

            DB::commit();

            // Log the main action
            Log::add(
                auth()->id(),
                'Buyurtma qabul qilindi',
                $oldData,
                $newData
            );

            return response()->json([
                'message' => 'Order received successfully',
                'order' => $order,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error receiving order: ' . $e->getMessage()], 500);
        }
    }

}