<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetOrderTailorResource;
use App\Models\Department;
use App\Models\Order;
use App\Models\OrderCut;
use App\Models\OrderGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TailorMasterController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()->employee->branch_id ?? null;

        $orders = Order::where('branch_id', $branchId)
        ->where(function ($query) {
            $query->where('status', 'printing')
                ->orWhere('status', 'cutting')
                ->orWhere('status', 'pending')
                ->orWhere('status', 'tailoring');
        })
            ->with([
                'orderModel',
                'orderModel.model',
                'orderModel.material',
                'orderModel.sizes.size',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group',
                'instructions'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $resource = GetOrderTailorResource::collection($orders);

        return response()->json($resource);
    }

    public function fasteningOrderToGroup(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = json_decode($request->getContent(), true);


        if (is_null($data)) {
            return response()->json([
                'message' => 'Invalid JSON format',
            ], 400);
        }

        $validator = validator($data, [
            'data' => 'required|array',
            'data.*.group_id' => 'required|integer|exists:groups,id',
            'data.*.order_id' => 'required|integer|exists:orders,id',
            'data.*.submodel_id' => 'required|integer|exists:order_sub_models,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $validatedData = $validator->validated();
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $e->errors(),
            ], 422);
        }

        foreach ($validatedData['data'] as $datum) {
            $groupId = $datum['group_id'];
            $orderId = $datum['order_id'];
            $submodelId = $datum['submodel_id'];

            if (OrderGroup::where('order_id', $orderId)->where('submodel_id', $submodelId)->exists()) {
                $group = OrderGroup::where('order_id', $orderId)->where('submodel_id', $submodelId)->first();
                $group->update([
                    'group_id' => $groupId,
                ]);
            }else{
                OrderGroup::create([
                    'group_id' => $groupId,
                    'order_id' => $orderId,
                    'submodel_id' => $submodelId,
                ]);
            }

        }

        return response()->json([
            'message' => 'Order fastened to group successfully',
        ], 200);
    }

    public function getGroups(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        $department = Department::where('responsible_user_id', $user->id)->first();

        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        $groups = $department->groups()->get();

        return response()->json($groups);
    }

}