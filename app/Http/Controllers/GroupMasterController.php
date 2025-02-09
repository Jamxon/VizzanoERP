<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetOrderGroupMasterResource;
use App\Http\Resources\GetTarificationGroupMasterResource;
use App\Models\Order;
use App\Models\Tarification;
use Illuminate\Http\Request;

class GroupMasterController extends Controller
{
    public function getOrders(Request $request)
    {
        $user = auth()->user();

        if (!$user->group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        $query = $user->group->orders()->with([
            'order.orderModel',
            'order.orderModel.model',
            'order.orderModel.material',
            'order.orderModel.sizes.size',
            'order.orderModel.submodels.submodel',
            'order.orderModel.submodels.group',
            'order.instructions'
        ]);

            $status = strtolower(trim($request->status));

            $query->whereHas('order', function ($q) use ($status) {
                $q->where('status', $status); // OrderGroup emas, Order modeldan olish!
            });



  return       $orders = $query->get();

        return response()->json(GetOrderGroupMasterResource::collection($orders));
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
            'order.orderModel.submodels.tarificationCategories.tarifications',
        ])->get();

        $resource = GetTarificationGroupMasterResource::collection($tarifications);

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
