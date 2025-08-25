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
                $query->whereHas('order.orderModel.submodels.sewingOutputs', function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                })->with(['order' => function ($query) {
                    $query->select('id', 'name', 'quantity', 'status', 'start_date', 'end_date');
                }, 'order.orderModel' => function ($query) {
                    $query->select('id', 'order_id', 'model_id', 'rasxod', 'status', 'minute')
                        ->with('model:id,name');
                }, 'order.orderModel.submodels' => function ($query) {
                    $query->select('id', 'order_model_id')
                        ->with('sewingOutputs');
                }]);
            }])
            ->get();

        return response()->json($groups);

    }
}