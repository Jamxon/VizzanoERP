<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetOrderCutResource;
use App\Http\Resources\GetSpecificationResource;
use App\Http\Resources\showOrderCuttingMasterResource;
use App\Models\Order;
use App\Models\OrderCut;
use App\Models\OrderModel;
use App\Models\OrderPrintingTimes;
use App\Models\Outcome;
use App\Models\OutcomeItemModelDistrubition;
use App\Models\Stok;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

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
                'orderModel.material',
                'orderModel.submodels',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size',
                'orderPrintingTime',
                'orderPrintingTime.user'
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

    public function acceptCompletedItem(Request $request): \Illuminate\Http\JsonResponse
    {
        $id = $request->id;
        DB::beginTransaction();
        try {
            $outcome = Outcome::findOrFail($id);

            $newStatus = $request->status;

            if ($newStatus == "cancelled") {
                foreach ($outcome->items as $item) {
                    $stock = Stok::where('warehouse_id', $outcome->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->firstOrFail();
                    $stock->quantity += $item->quantity;
                    $stock->save();
                }
            }

            if (in_array($newStatus, ["sent", "completed", "accepted"])) {
                foreach ($outcome->items as $item) {
                    $stock = Stok::where('warehouse_id', $outcome->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->first();

                    if (!$stock) {
                        return response()->json([
                            'error' => "{$item->product->name} mahsuloti omborda mavjud emas"
                        ], 400);
                    }

                    if ($stock->quantity < $item->quantity) {
                        return response()->json([
                            'error' => "{$item->product->name} mahsuloti omborda yetarli emas. Mavjud: {$stock->quantity}, Kerak: {$item->quantity}"
                        ], 400);
                    }
                }
            }

            $outcome->status = $newStatus;
            $outcome->save();

            if (in_array($newStatus, ["sent", "completed", "accepted"])) {
                foreach ($outcome->items as $item) {
                    $stock = Stok::where('warehouse_id', $outcome->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->firstOrFail();
                    $stock->quantity -= $item->quantity;
                    $stock->save();
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'data' => $outcome->load('items')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getSpecificationByOrderId($id): \Illuminate\Http\JsonResponse
    {
        $order = Order::find($id);

        $order->load([
            'orderModel.submodels.specificationCategories',
            'orderModel.submodels.specificationCategories.specifications'
        ]);

        $resource = new GetSpecificationResource($order);

        return response()->json($resource);
    }

    public function markAsCut(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderId = $request->order_id;
        $categoryId = $request->category_id;
        $quantity = $request->quantity;
        $user = auth()->user();

        OrderCut::create([
            'order_id' => $orderId,
            'specification_category_id' => $categoryId,
            'user_id' => $user->id,
            'cut_at' => Carbon::now(),
            'quantity' => $quantity,
        ]);

        return response()->json(["message" => "Cut marked successfully"]);
    }

    public function getCuts($id): \Illuminate\Http\JsonResponse
    {
        $cuts = OrderCut::where('order_id', $id)
            ->get();

        $groupedCuts = $cuts->groupBy(function ($cut) {
            return $cut->category->submodel->id ?? null;
        });

        $resource = $groupedCuts->map(function ($group, $submodelId) {
            $submodel = $group->first()->category->submodel;
            return [
                'submodel' => [
                    'id' => $submodel->submodel->id ?? null,
                    'name' => $submodel->submodel->name ?? null,
                ],
                'cuts' => $group->map(function ($cut) {
                    return [
                        'id' => $cut->id,
                        'cut_at' => $cut->cut_at,
                        'quantity' => $cut->quantity,
                        'category' => [
                            'id' => $cut->category->id,
                            'name' => $cut->category->name,
                        ],
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return response()->json($resource);
    }

    public function finishCutting($id): \Illuminate\Http\JsonResponse
    {
        $order = Order::find($id);
        $order->update([
            'status' => 'pending'
        ]);

        return response()->json([
            'message' => 'Order cutting finished'
        ]);
    }
}