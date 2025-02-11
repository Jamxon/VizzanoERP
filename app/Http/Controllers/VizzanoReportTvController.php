<?php

namespace App\Http\Controllers;

use App\Models\Motivation;
use App\Models\SewingOutputs;
use Illuminate\Http\Request;

class VizzanoReportTvController extends Controller
{
    public function getSewingOutputs(Request $request): \Illuminate\Http\JsonResponse
    {
        $startDate = $request->get('start_date') ?? now()->format('Y-m-d');
        $endDate = $request->get('end_date');
        $today = now()->format('Y-m-d');

        if (!$endDate) {
            $query = SewingOutputs::whereDate('created_at', '=', $startDate);
        } else {
            $query = SewingOutputs::whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate);
        }

        $sewingOutputs = $query
            ->selectRaw('order_submodel_id, SUM(quantity) as total_quantity, SUM(CASE WHEN DATE(created_at) = ? THEN quantity ELSE 0 END) as today_quantity', [$today])
            ->groupBy('order_submodel_id')
            ->with(['orderSubmodel.orderModel', 'orderSubmodel.submodel', 'orderSubmodel.group'])
            ->orderBy('total_quantity', 'desc')
            ->get();

        $motivations = Motivation::all()->map(fn($motivation) => [
            'title' => $motivation->title,
        ]);

        $resource = [
            'sewing_outputs' => $sewingOutputs->map(function ($sewingOutput) {
                return [
                    'model' => optional($sewingOutput->orderSubmodel->orderModel)->model,
                    'submodel' => $sewingOutput->orderSubmodel->submodel,
                    'group' => optional($sewingOutput->orderSubmodel->group)->group,
                    'total_quantity' => $sewingOutput->total_quantity,
                    'today_quantity' => $sewingOutput->today_quantity,
                ];
            }),
            'motivations' => $motivations,
        ];

        return response()->json($resource);
    }
}
