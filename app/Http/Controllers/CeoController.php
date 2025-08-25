<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CeoController extends Controller
{
    public function getGroupResult(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $groups = \App\Models\Group::where('department_id', $request->department_id)
            ->with(['orders' => function ($query) use ($startDate, $endDate) {
                $query->whereHas('order.orderModel.submodels.sewingOutputs', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                    ->with([
                        'order' => function ($q) {
                            $q->select('id', 'name', 'quantity', 'status', 'start_date', 'end_date');
                        },
                        'order.orderModel' => function ($q) {
                            $q->select('id', 'order_id', 'model_id', 'rasxod', 'status', 'minute')
                                ->with('model:id,name');
                        },
                        'order.orderModel.submodels' => function ($q) use ($startDate, $endDate) {
                            $q->select('id', 'order_model_id')
                                ->with(['sewingOutputs' => function ($sq) use ($startDate, $endDate) {
                                    $sq->whereBetween('created_at', [$startDate, $endDate])
                                        ->select(
                                            'id',
                                            'submodel_id',
                                            'quantity',
                                            'created_at'
                                        );
                                }])
                                // qo‘shimcha aggregatlar
                                ->withSum(['sewingOutputs as total_quantity' => function ($sq) use ($startDate, $endDate) {
                                    $sq->whereBetween('created_at', [$startDate, $endDate]);
                                }], 'quantity')
                                ->withMin(['sewingOutputs as min_date' => function ($sq) use ($startDate, $endDate) {
                                    $sq->whereBetween('created_at', [$startDate, $endDate]);
                                }], 'created_at')
                                ->withMax(['sewingOutputs as max_date' => function ($sq) use ($startDate, $endDate) {
                                    $sq->whereBetween('created_at', [$startDate, $endDate]);
                                }], 'created_at');
                        }
                    ]);
            }, 'responsibleUser.employee'])
            ->get();

        return response()->json($groups);
    }
}