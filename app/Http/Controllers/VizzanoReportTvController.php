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

        $query = SewingOutputs::query();

        if ($endDate) {
            // Agar `end_date` kelsa, start_date va end_date oralig'ini olamiz
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            // Agar `end_date` kelmasa, faqat `start_date` bo'yicha olish
            $query->whereDate('created_at', '=', $startDate);
            $today = $startDate; // today_quantity faqat shu sana bo'yicha bo'lishi uchun
        }

        // Umumiy natijalarni jamlash va bugungi natijalarni hisoblash
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
                    'total_quantity' => $sewingOutput->total_quantity, // Barcha natijalar yig'indisi
                    'today_quantity' => $sewingOutput->today_quantity, // Faqat bugungi natija
                ];
            }),
            'motivations' => $motivations,
        ];

        return response()->json($resource);
    }
}
