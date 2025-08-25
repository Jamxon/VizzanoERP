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
            ->whereHas('orders.order.orderModel.submodels.sewingOutputs', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->get();

        return response()->json($groups);

    }
}