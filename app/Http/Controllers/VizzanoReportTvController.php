<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
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
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $query->whereDate('created_at', '=', $startDate);
            $today = $startDate;
        }

        $groupIds = $query
            ->join('order_sub_models', 'sewing_outputs.order_submodel_id', '=', 'order_sub_models.id')
            ->join('order_groups', 'order_sub_models.id', '=', 'order_groups.submodel_id') // To‘g‘ri bog‘lash
            ->whereDate('sewing_outputs.created_at', '=', $startDate) // created_at aniq jadvaldan
            ->pluck('order_groups.group_id')
            ->unique();


        $sewingOutputs = $query
            ->selectRaw('order_submodel_id, SUM(quantity) as total_quantity, SUM(CASE WHEN DATE(created_at) = ? THEN quantity ELSE 0 END) as today_quantity', [$today])
            ->groupBy('order_submodel_id')
            ->with(['orderSubmodel.orderModel', 'orderSubmodel.submodel', 'orderSubmodel.group'])
            ->orderBy('total_quantity', 'desc')
            ->get();

        // Faqat SewingOutputs dagi group_id lar uchun ishchilar sonini olish
        $employeeCounts = Attendance::where('attendance.date', $today)
            ->where('attendance.status', '!=', 'ABSENT')
            ->join('employees', 'attendance.employee_id', '=', 'employees.id')
            ->whereIn('employees.group_id', $groupIds) // Faqat chiqqan group_id larni olish
            ->groupBy('employees.group_id')
            ->selectRaw('employees.group_id, COUNT(DISTINCT attendance.employee_id) as employee_count')
            ->pluck('employee_count', 'employees.group_id');

        $motivations = Motivation::all()->map(fn($motivation) => [
            'title' => $motivation->title,
        ]);

        $resource = [
            'sewing_outputs' => $sewingOutputs->map(function ($sewingOutput) use ($employeeCounts) {
                return [
                    'model' => optional($sewingOutput->orderSubmodel->orderModel)->model,
                    'submodel' => $sewingOutput->orderSubmodel->submodel,
                    'group' => optional($sewingOutput->orderSubmodel->group)->group,
                    'total_quantity' => $sewingOutput->total_quantity,
                    'today_quantity' => $sewingOutput->today_quantity,
                    'employee_count' => $employeeCounts[$sewingOutput->orderSubmodel->group_id] ?? 0,
                ];
            }),
            'motivations' => $motivations,
        ];

        return response()->json($resource);
    }

}
