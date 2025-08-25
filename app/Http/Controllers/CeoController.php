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
            ->with([
                'orders' => function ($query) use ($startDate, $endDate) {
                    // orders faqat sana bo‘yicha filter qilinadi
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
                            'order.orderModel.submodels' => function ($q) {
                                $q->select('id', 'order_model_id')
                                    ->with(['sewingOutputs' => function ($sq) {
                                        // ❌ Sana bo‘yicha filtr olib tashlandi
                                        $sq->select(
                                            'id',
                                            'order_submodel_id',
                                            'quantity',
                                            'created_at'
                                        );
                                    }])
                                    // Aggregatlar ham sanaga bog‘lanmaydi
                                    ->withSum('sewingOutputs as total_quantity', 'quantity')
                                    ->withMin('sewingOutputs as min_date', 'created_at')
                                    ->withMax('sewingOutputs as max_date', 'created_at');
                            }
                        ]);
                },
                'responsibleUser.employee'
            ])
            ->get();

        return response()->json($groups);
    }

}