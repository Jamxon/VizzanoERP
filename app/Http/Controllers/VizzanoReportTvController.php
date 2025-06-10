<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Motivation;
use App\Models\SewingOutputs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VizzanoReportTvController extends Controller
{
    public function getSewingOutputs(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $branchId = $user?->employee?->branch_id;

        if (!$branchId) {
            return response()->json(['message' => '❌ Foydalanuvchining filial (branch) aniqlanmadi.'], 422);
        }

        $startDate = $request->get('start_date') ?? now()->format('Y-m-d');
        $endDate = $request->get('end_date');
        $today = $startDate;

        // ✅ 1. Shu branchga tegishli group_id larni topamiz
        $groupIds = \App\Models\Group::whereHas('department.mainDepartment', function ($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        })->pluck('id');

        // ✅ 2. Group_id orqali order_submodel_id larni topamiz
        $orderSubmodelIds = \App\Models\OrderGroup::whereIn('group_id', $groupIds)
            ->pluck('submodel_id');

        // ✅ 3. Unga tegishli sewing_outputs larni yuklab olamiz
        $query = SewingOutputs::whereIn('order_submodel_id', $orderSubmodelIds);

        if ($endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $query->whereDate('created_at', $startDate);
        }

        $sewingOutputs = $query
            ->select('order_submodel_id')
            ->selectRaw("SUM(CASE WHEN DATE(created_at) = '{$today}' THEN quantity ELSE 0 END) as today_quantity")
            ->groupBy('order_submodel_id')
            ->with([
                'orderSubmodel.orderModel.model',
                'orderSubmodel.submodel',
                'orderSubmodel.group.group',
                'orderSubmodel.submodelSpend'
            ])
            ->get();

        $totalQuantities = SewingOutputs::whereIn('order_submodel_id', $orderSubmodelIds)
            ->select('order_submodel_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('order_submodel_id')
            ->pluck('total_quantity', 'order_submodel_id');

        $sewingOutputs->transform(function ($item) use ($totalQuantities) {
            $item->total_quantity = $totalQuantities[$item->order_submodel_id] ?? 0;
            return $item;
        });

        // ✅ 4. Attendance ma'lumotlari
        $employeeCounts = \App\Models\Attendance::whereDate('attendance.date', $today)
            ->where('attendance.status', '!=', 'ABSENT')
            ->join('employees', 'attendance.employee_id', '=', 'employees.id')
            ->whereIn('employees.group_id', $groupIds)
            ->groupBy('employees.group_id')
            ->selectRaw('employees.group_id, COUNT(DISTINCT attendance.employee_id) as employee_count')
            ->pluck('employee_count', 'employees.group_id');

        // ✅ 5. Work time hisoblash
        $workTimeByGroup = \App\Models\Group::whereIn('groups.id', $groupIds)
            ->join('departments', 'groups.department_id', '=', 'departments.id')
            ->selectRaw('
        groups.id as group_id, 
        EXTRACT(EPOCH FROM (departments.end_time - departments.start_time - (departments.break_time * INTERVAL \'1 second\'))) as work_seconds
    ')
            ->pluck('work_seconds', 'group_id');


        // ✅ 6. Motivatsiyalar
        $motivations = \App\Models\Motivation::all()->map(fn($m) => ['title' => $m->title]);

        // ✅ 7. Natijani yig'ish
        $resource = [
            'sewing_outputs' => $sewingOutputs->map(function ($output) use ($employeeCounts, $workTimeByGroup) {
                $group_id = optional($output->orderSubmodel->group->group)->id;
                $employeeCount = $employeeCounts[$group_id] ?? 0;
                $workTime = $workTimeByGroup[$group_id] ?? 0;
                $spend = optional($output->orderSubmodel->submodelSpend->first())->seconds;

                $today_plan = ($spend > 0 && $employeeCount > 0)
                    ? intval(($workTime * $employeeCount) / $spend)
                    : 0;

                return [
                    'model' => optional($output->orderSubmodel->orderModel)->model,
                    'order_id' => optional($output->orderSubmodel->orderModel->order)->id,
                    'submodel' => $output->orderSubmodel->submodel,
                    'group' => optional($output->orderSubmodel->group)->group,
                    'total_quantity' => $output->total_quantity,
                    'today_quantity' => $output->today_quantity,
                    'employee_count' => $employeeCount,
                    'today_plan' => $today_plan,
                    'mode' => $output->mode
                ];
            }),
            'motivations' => $motivations,
        ];

        return response()->json($resource);
    }
}
