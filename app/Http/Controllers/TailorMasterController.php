<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderCut;
use App\Models\OrderGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TailorMasterController extends Controller
{
    public function getOrders(): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('branch_id', auth()->user()->branch_id)
            ->orWhere('status', 'printing')
            ->orWhere('status', 'cutting')
            ->orWhere('status', 'pending')
            ->orWhere('status', 'tailoring')
            ->with([
                'orderModel',
                'orderModel.model',
                'orderModel.material',
                'orderModel.sizes.size',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group', // faqat asosiy groupni olish
                'instructions'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $orders = $orders->map(function ($order) {
            if ($order->orderModel) {
                $order->orderModel->submodels = collect($order->orderModel->submodels)->map(function ($submodel) {
                    if (isset($submodel['group']) && is_array($submodel['group'])) {
                        // Agar group ichida yana group bo'lsa, uni to'g'ri tekislaymiz
                        if (isset($submodel['group']['group']) && is_array($submodel['group']['group'])) {
                            $submodel['group'] = [
                                'id' => $submodel['group']['group']['id'] ?? null,
                                'name' => $submodel['group']['group']['name'] ?? null,
                            ];
                        } else {
                            // Agar group o'zi to'g'ridan-to'g'ri kelgan bo'lsa, shunchaki tekshiramiz
                            $submodel['group'] = [
                                'id' => $submodel['group']['id'] ?? null,
                                'name' => $submodel['group']['name'] ?? null,
                            ];
                        }
                    } else {
                        $submodel['group'] = null; // Agar group null bo'lsa, uni null qilib qo'yamiz
                    }
                    return $submodel;
                });
            }
            return $order;
        });



        return response()->json($orders);
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
                return response()->json([
                    'message' => 'Order fastened to group successfully',
                ], 200);
            }

            OrderGroup::create([
                'group_id' => $groupId,
                'order_id' => $orderId,
                'submodel_id' => $submodelId,
            ]);
        }

        return response()->json([
            'message' => 'Order fastened to group successfully',
        ], 200);
    }
}