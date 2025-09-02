<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CeoController extends Controller
{
    public function getGroupOrder(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $groups = \App\Models\Group::where('department_id', $request->department_id)
            ->with(['orders' => function ($query) use ($startDate, $endDate) {
                $query->whereHas('order.orderModel.submodels.sewingOutputs', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                    ->with([
                        'order:id,name',
                        'order.orderModel.submodels' => function ($q) {
                            $q->select('id', 'order_model_id', 'submodel_id')
                                ->with([
                                    // ⚡ bu yerda filtr qo‘ymaymiz
                                    'sewingOutputs:id,order_submodel_id,quantity,created_at',
                                    'submodel:id,name'
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
                    'total_sewn' => 0
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
                            'created_at' => $output->created_at->format('Y-m-d H:i:s')
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

}