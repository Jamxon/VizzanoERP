<?php

namespace App\Http\Controllers;

use App\Http\Resources\showOrderCuttingMasterResource;
use App\Models\Order;
use App\Models\OrderModel;
use App\Models\OrderPrintingTimes;
use App\Models\Outcome;
use App\Models\OutcomeItemModelDistrubition;
use Illuminate\Http\Request;

class CuttingMasterController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $status = $request->input('status');
        $orders = Order::where('status', $status)
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->whereDate('start_date', '<=', now()->addDays(15)->toDateString())
            ->orderBy('start_date', 'asc')
            ->with(
                'instructions',
                'orderModel.model',
                'orderModel.submodels',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size',
                'orderPrintingTime'
            )
            ->get();

        return response()->json($orders);
    }

    public function sendToConstructor(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'planned_time' => 'required|date',
            'comment' => 'nullable|string'
        ]);

        $order = Order::find($data['order_id']);

        $order->update([
            'status' => 'printing'
        ]);

        $orderPrintingTime = OrderPrintingTimes::create([
            'order_id' => $data['order_id'],
            'planned_time' => $data['planned_time'],
            'status' => 'printing',
            'comment' => $data['comment'],
            'user_id' => auth()->user()->id
        ]);

        return response()->json($orderPrintingTime);
    }

    public function getCompletedItems(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderId = $request->input('order_id');

        $orderModelIds = OrderModel::where('order_id', $orderId)->pluck('id')->toArray();

        $outcomeItemModelDistribution = OutcomeItemModelDistrubition::whereIn('model_id', $orderModelIds)
            ->whereHas('outcomeItem.outcome', function ($query) {
                $query->where('outcome_type', 'production')
                    ->whereHas('productionOutcome', function ($query) {
                        $query->where('received_by_id', auth()->id());
                    });
            })
            ->with([
                'outcomeItem.outcome.items.product:id,name',
                'orderModel:id,model_id,order_id',
                'orderModel.model:id,name',
                'orderModel.order:id,start_date',
                'orderModel.order'
            ])
            ->get();



        return response()->json($outcomeItemModelDistribution);
    }

    public function showOrder(Order $order): \Illuminate\Http\JsonResponse
    {
        $orderModelIds = OrderModel::where('order_id', $order->id)->pluck('id')->toArray();

        $outcomeItemModelDistribution = OutcomeItemModelDistrubition::whereIn('model_id', $orderModelIds)
            ->whereHas('outcomeItem.outcome', function ($query) {
                $query->where('outcome_type', 'production')
                    ->whereHas('productionOutcome', function ($query) {
                        $query->where('received_by_id', auth()->id());
                    });
            })
            ->with([
                'outcomeItem.outcome.items.product.color',
                'orderModel.model',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size',
                'orderModel.model',
                'orderModel.order.instructions',
                'orderModel.order.orderRecipes',
//                'orderModel.order.orderPrintingTime.user'
            ])
            ->get();

        $orderRecipes = $order->orderRecipes->map(function ($recipe) {
            return [
                'id' => $recipe->id,
                'quantity' => $recipe->quantity,
                'item' => $recipe->item->load('color','unit'),
            ];
        });

        $outcomes = [];
        foreach ($outcomeItemModelDistribution as $item) {
            $outcome = $item->outcomeItem->outcome;
            $outcomeId = $outcome->id;

            if (!isset($outcomes[$outcomeId])) {
                $outcomes[$outcomeId] = [
                    'id' => $outcome->id,
                    'outcome_type' => $outcome->outcome_type,
                    'number' => $outcome->number,
                    'status' => $outcome->status,
                    'items' => [],
                ];
            }

            foreach ($outcome->items as $outcomeItem) {
                $itemId = $outcomeItem->id;
                if (!isset($outcomes[$outcomeId]['items'][$itemId])) {
                    $outcomes[$outcomeId]['items'][$itemId] = [
                        'id' => $outcomeItem->id,
                        'name' => $outcomeItem->product->name ?? null,
                        'code' => $outcomeItem->product->code ?? null,
                        'quantity' => $outcomeItem->quantity ?? 0,
                        'unit' => $outcomeItem->product->unit ?? null,
                        'color' => [
                            'id' => $outcomeItem->product->color->id ?? null,
                            'name' => $outcomeItem->product->color->name ?? null,
                            'hex' => $outcomeItem->product->color->hex ?? null,
                        ]
                    ];
                }
            }
        }

        $outcomes = array_map(function ($outcome) {
            $outcome['items'] = array_values($outcome['items']);
            return $outcome;
        }, array_values($outcomes));

        $resource = new showOrderCuttingMasterResource($order);
        $resource->outcomes = $outcomes;

        return response()->json($resource);
    }


    public function acceptCompletedItem($id): \Illuminate\Http\JsonResponse
    {
        $outcome = Outcome::find($id);

        $outcome->update([
            'status' => 'accepted'
        ]);

        return response()->json($outcome);
    }

    public function cancelCompletedItem($id): \Illuminate\Http\JsonResponse
    {
        $outcome = Outcome::find($id);

        $outcome->update([
            'status' => 'cancelled'
        ]);

        return response()->json($outcome);
    }
}
