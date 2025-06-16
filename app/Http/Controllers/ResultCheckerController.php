<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;

class ResultCheckerController extends Controller
{
    public function getGroups(Request $request): \Illuminate\Http\JsonResponse
    {
        $groups = Group::where('department_id', $request->input('department_id'))
            ->whereHas('orders.orderSubmodel.sewingOutputs', function ($query) {
                $query->whereDate('created_at', date('Y-m-d'));
            })
            ->with([
                'responsibleUser',
                'orders.orderSubmodel.submodel',
                'orders.order',
                'orders.orderSubmodel.sewingOutputs' => function ($query) {
                    $query->whereDate('created_at', date('Y-m-d'));
                }
            ])
            ->get()
            ->map(function ($group) {
                // Yig'indi hisoblash
                $totalQuantity = 0;
                foreach ($group->orders as $order) {
                    foreach ($order->orderSubmodel->sewingOutputs ?? [] as $output) {
                        $totalQuantity += $output->quantity;
                    }
                }

                $group->total_quantity = $totalQuantity;
                return $group;
            });

        return response()->json($groups);
    }
}