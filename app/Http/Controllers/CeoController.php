<?php

namespace App\Http\Controllers;

use App\Models\MonthlySelectedOrder;
use App\Models\Order;
use Illuminate\Http\Request;

class CeoController extends Controller
{
    public function getMonthlySelectedOrders(Request $request)
    {
        $branchId = auth()->user()->employee->branch_id;

        $query = MonthlySelectedOrder::with([
            'order.orderModel.submodels' => function ($q) {
                $q->withSum('sewingOutputs', 'quantity');
            },
            'order.orderModel.submodels.submodel',
            'order.orderModel.model',
            'order.orderModel.submodels.group.group',
            ])->whereHas('order', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);

        });

        if ($request->filled('month')) {
            $query->whereMonth('month', date('m', strtotime($request->month)))
                ->whereYear('month', date('Y', strtotime($request->month)));
        }

        $records = $query->get()->map(function ($item) {
            $order = $item->order;

            $doneQuantity = 0;
            if ($order && $order->orderModel) {
                foreach ($order->orderModel->submodels as $submodel) {
                    $doneQuantity += $submodel->sewing_outputs_sum_quantity ?? 0;
                }
            }

            $order->done_quantity = $doneQuantity;

            return $item;
        });

        // ✅ Recommendation (branchdagi, lekin monthly_selected_orders jadvalida yo‘q bo‘lganlar)
        $selectedOrderIds = $records->pluck('order_id');

        $allSelectedOrderIds = MonthlySelectedOrder::pluck('order_id')->toArray();

        $recommendations = Order::with(['orderModel.submodels' => function ($q) {
            $q->withSum('sewingOutputs', 'quantity');
        },
            'orderModel.submodels.submodel',
            'orderModel.model',
            'orderModel.submodels.group.group',
            ])
            ->where('branch_id', $branchId) // ✅ branch filter
            ->whereIn('status', ['cutting', 'pending', 'tailoring', 'tailored']) // ✅ status filter
            ->whereNotIn('id', $allSelectedOrderIds) // ✅ ro‘yxatda yo‘q bo‘lganlarni olamiz
            ->get()
            ->map(function ($order) {
                $doneQuantity = 0;
                if ($order->orderModel) {
                    foreach ($order->orderModel->submodels as $submodel) {
                        $doneQuantity += $submodel->sewing_outputs_sum_quantity ?? 0;
                    }
                }
                $order->done_quantity = $doneQuantity;
                return $order;
            });

        return response()->json([
            'selected' => $records,
            'recommendations' => $recommendations,
        ]);
    }

    public function getGroupOrder(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // ✅ Faqat MonthlySelectedOrder jadvalidagi order_id larni olish
        $selectedOrderIds = \App\Models\MonthlySelectedOrder::query()
        ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
            $q->whereBetween('month', [$request->start_date, $request->end_date]);
        })
        ->pluck('order_id')
        ->toArray();

        $groups = \App\Models\Group::where('department_id', $request->department_id)
            ->with(['orders' => function ($query) use ($startDate, $endDate, $selectedOrderIds) {
                $query->whereIn('order_id', $selectedOrderIds) // ✅ faqat selected orderlar
                    // ->whereHas('order.orderModel.submodels.sewingOutputs', function ($q) use ($startDate, $endDate) {
                    //     $q->whereBetween('created_at', [$startDate, $endDate]);
                    // })
                    ->with([
                        'order:id,name,quantity',
                        'order.orderModel.submodels' => function ($q) {
                            $q->select('id', 'order_model_id', 'submodel_id')
                                ->with([
                                    // 'sewingOutputs:id,order_submodel_id,quantity,created_at',
                                    'submodel:id,name',
                                    'sewingOutputs.time:id,time',
                                ]);
                        },
                    ]);
            }])
            ->get();

        $result = [];

        foreach ($groups as $group) {
            $groupData = [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'orders' => []
            ];

            foreach ($group->orders as $order) {
                $orderData = [
                    'order_id' => $order->order->id,
                    'order_name' => $order->order->name,
                    'status' => $order->status,
                    'submodels' => [],
                    'total_sewn' => 0,
                    'order_quantity' => $order->order->quantity,
                ];

                foreach ($order->order->orderModel->submodels as $submodel) {
                    $submodelData = [
                        'submodel_id' => $submodel->id,
                        'submodel_name' => optional($submodel->submodel)->name ?? 'N/A',
                        'total_sewn' => 0,
                        'outputs' => []
                    ];

                    foreach ($submodel->sewingOutputs as $output) {
                        $submodelData['total_sewn'] += $output->quantity;
                        $orderData['total_sewn'] += $output->quantity;

                        $submodelData['outputs'][] = [
                            'id' => $output->id,
                            'quantity' => $output->quantity,
                            'time' => $output->time,
                            'created_at' => $output->created_at
                        ];
                    }

                    if ($submodelData['total_sewn'] > 0) {
                        $orderData['submodels'][] = $submodelData;
                    }
                }

                if ($orderData['total_sewn'] > 0) {
                    $groupData['orders'][] = $orderData;
                }
            }

            if (!empty($groupData['orders'])) {
                $result[] = $groupData;
            }
        }

        return response()->json([
            'groups' => $result
        ]);
    }


    public function storeMonthlySelectedOrders(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'month' => 'required|date',
        ]);

        $record = MonthlySelectedOrder::create($data);

        return response()->json($record, 201);
    }

    public function updateMonthlySelectedOrders(Request $request, $id)
    {
        $record = MonthlySelectedOrder::findOrFail($id);

        $data = $request->validate([
            'order_id' => 'sometimes|exists:orders,id',
            'month' => 'sometimes|date',
        ]);

        $record->update($data);

        return response()->json($record);
    }

    public function destroyMonthlySelectedOrders($id)
    {
        $record = MonthlySelectedOrder::findOrFail($id);
        $record->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}