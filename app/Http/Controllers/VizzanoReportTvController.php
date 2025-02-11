<?php

namespace App\Http\Controllers;

use App\Models\Motivation;
use App\Models\SewingOutputs;
use Illuminate\Http\Request;

class VizzanoReportTvController extends Controller
{
    public function getSewingOutputs(Request $request): \Illuminate\Http\JsonResponse
    {
        $startDate = $request->get('start_date') ?? now()->subDays(6)->format('Y-m-d'); // Default: oxirgi 7 kun
        $endDate = $request->get('end_date') ?? null; // Bugungi sana
        $today = now()->format('Y-m-d'); // Bugungi sana

        $query = SewingOutputs::whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate);

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        // Guruh va submodel bo'yicha umumiy va bugungi ishlab chiqarilgan miqdorlarni hisoblash
        $sewingOutputs = $query
            ->selectRaw('order_submodel_id, SUM(quantity) as total_quantity, SUM(CASE WHEN DATE(created_at) = ? THEN quantity ELSE 0 END) as today_quantity', [$today])
            ->groupBy('order_submodel_id')
            ->with(['orderSubmodel.orderModel', 'orderSubmodel.submodel', 'orderSubmodel.group'])
            ->orderBy('total_quantity', 'desc')
            ->get();

        // Motivatsiyalarni olish
        $motivations = Motivation::all()->map(fn($motivation) => [
            'title' => $motivation->title,
        ]);

        // Natijani shakllantirish
        $resource = [
            'sewing_outputs' => $sewingOutputs->map(function ($sewingOutput) {
                return [
                    'model' => optional($sewingOutput->orderSubmodel->orderModel)->model,
                    'submodel' => $sewingOutput->orderSubmodel->submodel,
                    'group' => optional($sewingOutput->orderSubmodel->group)->group,
                    'total_quantity' => $sewingOutput->total_quantity,
                    'today_quantity' => $sewingOutput->today_quantity, // Faqat bugungi natija
                ];
            }),
            'motivations' => $motivations,
        ];

        return response()->json($resource);
    }



}
