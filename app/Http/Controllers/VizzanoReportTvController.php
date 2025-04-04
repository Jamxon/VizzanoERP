<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Motivation;
use App\Models\SewingOutputs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VizzanoReportTvController extends Controller
{
    public function getSewingOutputs(Request $request)
    {
        $startDate = $request->get('start_date') ?? now()->format('Y-m-d');
        $endDate = $request->get('end_date');
        $today = now()->format('Y-m-d');

        $query = SewingOutputs::query();

        if ($endDate) {
            $query->whereBetween('sewing_outputs.created_at', [$startDate, $endDate]);
        } else {
            $query->whereDate('sewing_outputs.created_at', '=', $startDate);
            $today = $startDate;
        }

        $groupIds = SewingOutputs::join('order_sub_models', 'sewing_outputs.order_submodel_id', '=', 'order_sub_models.id')
            ->join('order_groups', 'order_sub_models.id', '=', 'order_groups.submodel_id')
            ->whereDate('sewing_outputs.created_at', '=', $startDate)
            ->pluck('order_groups.group_id')
            ->unique();



        $sewingOutputs = $query
            ->select('order_submodel_id')
            ->selectRaw("SUM(CASE WHEN DATE(sewing_outputs.created_at) = '{$today}' THEN quantity ELSE 0 END) as today_quantity")
            ->groupBy('order_submodel_id')
            ->with([
                'orderSubmodel.orderModel',
                'orderSubmodel.submodel',
                'orderSubmodel.group',
                'orderSubmodel.submodelSpend'
            ])
            ->get();

        $totalQuantities = \App\Models\SewingOutputs::select('order_submodel_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('order_submodel_id')
            ->pluck('total_quantity', 'order_submodel_id');

        $sewingOutputs->transform(function ($item) use ($totalQuantities) {
            $item->total_quantity = $totalQuantities[$item->order_submodel_id] ?? 0;
            return $item;
        });


        $employeeCounts = Attendance::whereDate('attendance.date', $today)
            ->where('attendance.status', '!=', 'ABSENT')
            ->join('employees', 'attendance.employee_id', '=', 'employees.id')
            ->whereIn('employees.group_id', $groupIds)
            ->groupBy('employees.group_id')
            ->selectRaw('employees.group_id, COUNT(DISTINCT attendance.employee_id) as employee_count')
            ->pluck('employee_count', 'employees.group_id');

        $workTimeByGroup = \App\Models\Group::whereIn('groups.id', $groupIds)
            ->join('departments', 'groups.department_id', '=', 'departments.id')
            ->selectRaw('
        groups.id as group_id, 
        EXTRACT(EPOCH FROM (departments.end_time - departments.start_time - (departments.break_time * INTERVAL \'1 second\'))) as work_seconds
    ')
            ->pluck('work_seconds', 'group_id');



        $motivations = Motivation::all()->map(fn($motivation) => [
            'title' => $motivation->title,
        ]);

        $resource = [
            'sewing_outputs' => $sewingOutputs->map(function ($sewingOutput) use ($employeeCounts, $workTimeByGroup) {
                dd($group_id = optional($sewingOutput->orderSubmodel->group->group)->id);
                $employeeCount = $employeeCounts[$group_id] ?? 0;
                $workTime = $workTimeByGroup[$group_id] ?? 0; // Ish vaqti soniyalarda
                $submodelSpend = optional($sewingOutput->orderSubmodel->submodelSpend->first())->seconds;

                $today_plan = ($submodelSpend > 0 && $employeeCount > 0)
                    ? intval(($workTime * $employeeCount) / $submodelSpend)
                    : 0;

                return [
                    'model' => optional($sewingOutput->orderSubmodel->orderModel)->model,
                    'submodel' => $sewingOutput->orderSubmodel->submodel,
                    'group' => optional($sewingOutput->orderSubmodel->group)->group,
                    'total_quantity' => $sewingOutput->total_quantity,
                    'today_quantity' => $sewingOutput->today_quantity,
                    'employee_count' => $employeeCount,
                    'today_plan' => $today_plan,
                ];
            }),
            'motivations' => $motivations,
        ];

        return response()->json($resource);
    }

}
