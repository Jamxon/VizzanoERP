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

        $query = SewingOutputs::whereDate('created_at', '>=', $startDate);

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        // Guruh va submodel bo'yicha umumiy va bugungi ishlab chiqarilgan miqdorlarni hisoblash
        $sewingOutputs = $query
            ->selectRaw('order_submodel_id, SUM(quantity) as total_quantity, SUM(CASE WHEN DATE(created_at) = ? THEN quantity ELSE 0 END) as today_quantity', [$startDate])
            ->groupBy('order_submodel_id')
            ->with(['orderSubmodel.orderModel', 'orderSubmodel.submodel', 'orderSubmodel.group'])
            ->orderBy('total_quantity', 'desc')
            ->get();

        $motivations = Motivation::all()->map(fn($motivation) => [
            'title' => $motivation->title,
        ]);

        // Natijani shakllantirish
        $resource = [
            'sewing_outputs' => $sewingOutputs->map(function ($sewingOutput) {
                return [
                    'id' => $sewingOutput->id,
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
