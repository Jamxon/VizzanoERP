<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetOrderGroupMasterResource;
use App\Http\Resources\GetTarificationGroupMasterResource;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Tarification;
use http\Env\Response;
use Illuminate\Http\Request;

class GroupMasterController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        if (!$user->group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $status = strtolower(trim($request->status));

        $query = OrderGroup::where('group_id', $user->group->id)
            ->whereHas('order', function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->with([
                'order.orderModel',
                'order.orderModel.model',
                'order.orderModel.material',
                'order.orderModel.sizes.size',
                'order.orderModel.submodels.submodel',
                'order.orderModel.submodels.group',
                'order.instructions'
            ])
            ->selectRaw('DISTINCT ON (order_id) *');

        $orders = $query->get();

        return response()->json(GetOrderGroupMasterResource::collection($orders));
    }

    public function showOrder($id)
    {
        $order = Order::where('id', $id)
            ->with([
                'orderModel',
                'orderModel.model',
                'orderModel.material',
                'orderModel.sizes.size',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group',
                'instructions'
            ])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($order);
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

    public function getTarifications($id): \Illuminate\Http\JsonResponse
    {
        // Orderni topish
        $order = Order::where('id', $id)
            ->with([
                'orderModel.submodels.tarificationCategories'
            ])
            ->first();

        // Agar order topilmasa, 404 qaytarish
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Resurs orqali chiqarish
        $resource = new GetTarificationGroupMasterResource($order);

        return response()->json($resource);
    }



    public function startOrder($id): \Illuminate\Http\JsonResponse
    {
        $order = Order::find($id);

        $order->update([
            'status' => 'tailoring'
        ]);

        return response()->json([
            'message' => "Order successful started",
            'order' => $order
        ]);
    }

    public function assignEmployeesToTarifications(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->input('data');

        foreach ($data as $item) {
            $tarificationId = $item['tarification_id'];
            $userIds = $item['user_id'];

            $tarification = Tarification::find($tarificationId);

            if ($tarification) {
                $existingIds = $tarification->user_ids ? json_decode($tarification->user_ids, true) : [];
                $updatedIds = array_unique(array_merge($existingIds, $userIds));

                $tarification->update([
                    'user_ids' => json_encode($updatedIds)
                ]);
            }
        }

        return response()->json([
            'message' => 'Employees assigned to tarifications successfully'
        ]);
    }

}
