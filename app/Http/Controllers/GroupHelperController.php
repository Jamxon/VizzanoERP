<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\GetOrderGroupMasterResource;
use App\Http\Resources\ShowOrderGroupMaster;
use App\Models\Bonus;
use App\Models\Employee;
use App\Models\ExampleOutputs;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\OrderModel;
use App\Models\OrderSubModel;
use App\Models\SewingOutputs;
use App\Models\Time;
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

                $totalExample = $order->orderModel->submodels->flatMap(function ($submodel) {
                    return $submodel->exampleOutputs;
                })->sum('quantity');

                // Dinamik property qo‘shish yoki resource ichida ishlatish
                $order->total_sewn_quantity = $totalSewn;
                $order->total_example_quantity = $totalExample;
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

    public function storeExampleOutput(Request $request): \Illuminate\Http\JsonResponse
    {
        $validatedData = $request->validate([
            'order_submodel_id' => 'required|exists:order_sub_models,id',
            'quantity' => 'required|integer|min:0',
            'time_id' => 'required|exists:times,id',
            'comment' => 'nullable|string',
        ]);

        $orderSubModel = OrderSubModel::find($validatedData['order_submodel_id']);
        $orderModel = OrderModel::find($orderSubModel->order_model_id);
        $order = Order::find($orderModel->order_id);

        if (!$order) {
            return response()->json(['message' => 'Buyurtma topilmadi!'], 404);
        }

        $orderQuantity = $order->quantity;
        $totalSewnQuantity = ExampleOutputs::where('order_submodel_id', $orderSubModel->id)->sum('quantity');
        $newQuantity = $validatedData['quantity'];
        $combinedQuantity = $totalSewnQuantity + $newQuantity;

        if ($combinedQuantity > $orderQuantity) {
            $a = $orderQuantity - $totalSewnQuantity;
            return response()->json([
                'message' => "Siz faqat $a dona qo'shishingiz mumkin. Buyurtma umumiy miqdori: {$orderQuantity}, allaqachon tikilgan: {$totalSewnQuantity}."
            ], 400);
        }

        // Yangi natijani saqlash
        $exampleOutput = ExampleOutputs::create($validatedData);
        
        $time = Time::find($validatedData['time_id']);
        Log::add(
            auth()->id(),
            'Patok Master zagatovka natija kiritdi',
            'sewing',
            null,
            [
                'example_output' => $exampleOutput->id,
                'order_submodel' => $orderSubModel->submodel->name ?? 'Noma’lum submodel',
                'quantity' => $validatedData['quantity'],
                'time' => $time,
                'comment' => $validatedData['comment'] ?? null,
                'order' => $order->name ?? 'Noma’lum buyurtma',
                'remaining_quantity' => $orderQuantity - $combinedQuantity,
            ]
        );

        return response()->json([
            'message' => "Natija muvaffaqiyatli qo'shildi. Qolgan miqdor: " . ($orderQuantity - $combinedQuantity)
        ]);
    }

    public function showOrder($id): \Illuminate\Http\JsonResponse
    {
        $order = Order::where('id', $id)
            ->with([
                'orderModel',
                'orderModel.model',
                'orderModel.material',
                'orderModel.sizes.size',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group.group',
                'instructions',
                'orderModel.submodels.sewingOutputs',
                'orderModel.submodels.exampleOutputs',
            ])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $orderGroups = OrderGroup::where('order_id', $id)
            ->where('group_id', auth()->user()->employee->group->id)
            ->get();

        if ($order->orderModel) {
            $linkedSubmodelIds = $orderGroups->pluck('submodel_id');

            $order->orderModel->submodels = $order->orderModel->submodels
                ->whereIn('id', $linkedSubmodelIds)
                ->values();
        }

        return response()->json(new ShowOrderGroupMaster($order));
    }
}