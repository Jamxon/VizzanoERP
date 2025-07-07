<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
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

        $groupIds = \App\Models\Group::whereHas('department.mainDepartment', function ($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        })->pluck('id');

        $orderSubmodelIds = \App\Models\OrderGroup::whereIn('group_id', $groupIds)
            ->pluck('submodel_id');

        $query = SewingOutputs::whereIn('order_submodel_id', $orderSubmodelIds);

        if ($endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $query->whereDate('created_at', $startDate);
        }

        $aup = Attendance::whereDate('date', date('Y-m-d'))
            ->whereHas('employee', function ($q) use ($branchId)  {
            $q->where('status', '!=', 'kicked');
            $q->where('type', 'aup');
            $q->where('branch_id', $branchId);
        })->count();

        $simple = Attendance::whereDate('date', date('Y-m-d'))
            ->whereHas('employee', function ($q) use ($branchId)  {
                $q->where('status', '!=', 'kicked');
                $q->where('type', 'simple');
                $q->where('branch_id', $branchId);
            })->count();

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

        $latestOutputs = SewingOutputs::whereIn('order_submodel_id', $orderSubmodelIds)
            ->select('id', 'order_submodel_id', 'time_id')
            ->whereIn(DB::raw('(order_submodel_id, created_at)'), function ($query) use ($orderSubmodelIds) {
                $query->selectRaw('DISTINCT ON (order_submodel_id) order_submodel_id, created_at')
                    ->from('sewing_outputs')
                    ->whereIn('order_submodel_id', $orderSubmodelIds)
                    ->orderBy('order_submodel_id')
                    ->orderByDesc('created_at');
            })
            ->with('time')
            ->get()
            ->keyBy('order_submodel_id');

        $sewingOutputs->transform(function ($item) use ($totalQuantities, $latestOutputs) {
            $item->total_quantity = $totalQuantities[$item->order_submodel_id] ?? 0;
            $item->latest_time = optional($latestOutputs[$item->order_submodel_id]?->time)->time;
            return $item;
        });

        $employeeCounts = \App\Models\Attendance::whereDate('attendance.date', $today)
            ->where('attendance.status', '!=', 'ABSENT')
            ->join('employees', 'attendance.employee_id', '=', 'employees.id')
            ->whereIn('employees.group_id', $groupIds)
            ->whereHas('employee', function ($q) {
                $q->where('status', '!=', 'kicked');
            })
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

        $exampleQuery = \App\Models\ExampleOutputs::whereIn('order_submodel_id', $orderSubmodelIds);

        if ($endDate) {
            $exampleQuery->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $exampleQuery->whereDate('created_at', $startDate);
        }

        $exampleGrouped = $exampleQuery
            ->select('order_submodel_id')
            ->selectRaw("SUM(CASE WHEN DATE(created_at) = '{$today}' THEN quantity ELSE 0 END) as today_quantity")
            ->groupBy('order_submodel_id')
            ->with([
                'orderSubmodel.orderModel.model',
                'orderSubmodel.submodel',
                'orderSubmodel.group.group',
            ])
            ->get();

        $exampleTotalQuantities = \App\Models\ExampleOutputs::whereIn('order_submodel_id', $orderSubmodelIds)
            ->select('order_submodel_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('order_submodel_id')
            ->pluck('total_quantity', 'order_submodel_id');

        $exampleGrouped->transform(function ($item) use ($exampleTotalQuantities) {
            $item->total_quantity = $exampleTotalQuantities[$item->order_submodel_id] ?? 0;
            return $item;
        });

        $exampleOutputsData = $exampleGrouped->map(function ($output) {
            return [
                'model' => optional($output->orderSubmodel->orderModel)->model,
                'order_id' => optional($output->orderSubmodel->orderModel->order)->id,
                'submodel' => $output->orderSubmodel->submodel,
                'group' => optional($output->orderSubmodel->group)->group,
                'responsibleUser' => optional($output->orderSubmodel->group)->group->responsibleUser->employee->name,
                'today_quantity' => $output->today_quantity,
                'total_quantity' => $output->total_quantity,
            ];
        });

        // ✅ 6. Motivatsiyalar
        $motivations = \App\Models\Motivation::all()->map(fn($m) => ['title' => $m->title]);

        $groupEarnings = collect();

        // Bugun sewing qilgan submodellarga tegishli group_id larni topamiz
        $activeGroupIds = SewingOutputs::whereIn('order_submodel_id', $orderSubmodelIds)
            ->whereDate('created_at', $today)
            ->with('orderSubmodel.group') // Eager loading orqali
            ->get()
            ->pluck('orderSubmodel.group.group_id') // group_id larini yig'ish
            ->unique()
            ->filter()
            ->values();


        foreach ($activeGroupIds as $groupId) {
            // ❗️ Faqat bugun natija kiritilgan submodel_id larni olish
                $todaySewn = SewingOutputs::whereHas('orderSubmodel.group', function ($q) use ($groupId) {
                    $q->where('group_id', $groupId);
                })
                    ->whereDate('created_at', $today)
                    ->select('order_submodel_id', DB::raw('SUM(quantity) as quantity'))
                    ->groupBy('order_submodel_id')
                    ->get();

                // ❗️ Faqat shu kiritilgan submodel_id larni rasxodlari
                $submodelIds = $todaySewn->pluck('order_submodel_id');

                $rasxodlar = \App\Models\OrderSubModel::with('orderModel')
                    ->whereIn('id', $submodelIds)
                    ->get()
                    ->pluck('orderModel.rasxod', 'id');

                // ❗️Bugungi umumiy topilgan pul = faqat natija kiritilgan submodellarning (miqdor * rasxod)
                $todayEarning = $todaySewn->sum(function ($row) use ($rasxodlar) {
                    $rasxod = $rasxodlar[$row->order_submodel_id] ?? 0;
                    return $row->quantity * $rasxod;
                });

                $todayEarning = round($todayEarning, 2);

                // ❗️Bugun shu groupda ishlagan xodimlar soni
                $attendanceCount = \App\Models\Attendance::whereDate('date', $today)
                    ->whereHas('employee', function ($q) use ($groupId) {
                        $q->where('status', '!=', 'kicked')
                            ->where('group_id', $groupId);
                    })
                    ->where('status', '!=', 'ABSENT')
                    ->count();

                // ❗️Bugun ishlagan har bir xodimga tushadigan o‘rtacha summa
                $perEmployeeEarning = $attendanceCount > 0
                    ? round($todayEarning / $attendanceCount, 2)
                    : 0;

                $groupEarnings->push([
                    'group_id' => $groupId,
                    'group_name' => optional(\App\Models\Group::find($groupId))->name,
                    'responsibleUser' => optional(\App\Models\Group::find($groupId)->responsibleUser->employee)->name,
                    'quantity' => $todaySewn->sum('quantity'),
                    'today_earning' => $todayEarning,
                    'attendance_count' => $attendanceCount,
                    'per_employee_earning' => $perEmployeeEarning,
                ]);
            }

        // ✅ 7. Natijani yig'ish
        $resource = [
            'sewing_outputs' => $sewingOutputs->map(function ($output) use ($aup, $employeeCounts, $workTimeByGroup) {
                $group_id = optional($output->orderSubmodel->group->group)->id;
                $employeeCount = $employeeCounts[$group_id] ?? 0;
                $workTime = $workTimeByGroup[$group_id] ?? 0;
                $spend = (optional($output->orderSubmodel)->orderModel->rasxod / 250) * 60;

                $today_plan = ($spend > 0 && $employeeCount > 0)
                    ? intval(($workTime * $employeeCount) / $spend)
                    : 0;

                return [
                    'model' => optional($output->orderSubmodel->orderModel)->model,
                    'order_id' => optional($output->orderSubmodel->orderModel->order)->id,
                    'submodel' => $output->orderSubmodel->submodel,
                    'group' => optional($output->orderSubmodel->group)->group,
                    'responsibleUser' => optional($output->orderSubmodel->group)->group->responsibleUser->employee->name,
                    'total_quantity' => $output->total_quantity,
                    'today_quantity' => $output->today_quantity,
                    'employee_count' => $employeeCount,
                    'today_plan' => $today_plan,
                    'last_time' => $output->latest_time,

                ];
            }),
            'motivations' => $motivations,
            'aup' => $aup,
            'simple' => $simple,
            'example_outputs' => $exampleOutputsData,
            'group_earnings' => $groupEarnings,
        ];

        return response()->json($resource);
    }

    public function getGroupPlans(Request $request)
    {
        $branchId = auth()->user()?->employee?->branch_id;

        if (!$branchId) {
            return response()->json(['message' => '❌ Foydalanuvchining filial (branch) aniqlanmadi.'], 422);
        }

        $month = $request->input('month');
        $year = $request->input('year');

        $groupPlans = \App\Models\GroupPlan::whereMonth('month', $month)
            ->whereYear('year', $year)
            ->whereHas('group.department.mainDepartment', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->with(['group', 'group.department'])
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'group_id' => $plan->group_id,
                    'group_name' => optional($plan->group)->name,
                    'quantity' => $plan->quantity,
                ];
            });

    }
}
