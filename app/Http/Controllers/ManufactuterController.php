<?php

namespace App\Http\Controllers;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Order;
use Illuminate\Http\Request;

class ManufactuterController extends Controller
{
    public function getBranchGroupsWithBudgets(Request $request): \Illuminate\Http\JsonResponse
    {
        $employee = auth()->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $branchId = $employee->branch_id;
        $selectedMonth = $request->month ?? now()->format('Y-m-01');

        $groups = Group::whereHas('department.mainDepartment', function($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        })
            ->with([
                'department.mainDepartment',
                'responsibleUser.employee',
                'employees' => fn($q) => $q->where('status', 'working')
            ])
            ->get();

        if ($groups->isEmpty()) {
            return response()->json(['message' => 'Hech qanday group topilmadi.'], 404);
        }

        $today = now();
        $result = [];

        foreach ($groups as $group) {
            $groupId = $group->id;

            $monthlyOrders = Order::where('branch_id', $branchId)
                ->whereHas('orderGroups', fn($q) => $q->where('group_id', $groupId))
                ->whereHas('monthlySelectedOrder', fn($q) => $q
                    ->whereMonth('month', date('m', strtotime($selectedMonth)))
                    ->whereYear('month', date('Y', strtotime($selectedMonth))))
                ->with('orderModel.model', 'orderModel.submodels.sewingOutputs')
                ->get();

            // --- Shu oy ichidagi barcha sewingOutputs quantity yig'indisi
            $monthlySewingOutputsSum = $monthlyOrders->sum(function($order) {
                return $order->orderModel->submodels
                    ->flatMap(fn($sub) => $sub->sewingOutputs)
                    ->sum('quantity');
            });

            $attendanceCount = Attendance::whereHas('employee', fn($q) =>
            $q->where('branch_id', $branchId)->where('group_id', $groupId)
            )->whereIn('date', function() use ($today) {
                $dates = collect();
                $date = now();
                while ($dates->count() < 30) {
                    if (!$date->isSunday()) $dates->push($date->toDateString());
                    $date->subDay();
                }
                return $dates;
            })->where('status', 'present')->count();

            $avgWorkers = $attendanceCount / 30;
            $dailyProductionMinutes = $avgWorkers * 500;

            $monthlyMinutesTotal = $monthlyOrders->sum(function($order) {
                $produced = $order->orderModel->submodels->flatMap(fn($s) => $s->sewingOutputs)->sum('quantity');
                $remaining = max($order->quantity - $produced, 0);
                return $order->orderModel->model->minute * $remaining;
            });

            $monthlyDaysToFinish = $dailyProductionMinutes > 0 ? ceil($monthlyMinutesTotal / $dailyProductionMinutes) : null;
            $monthlyTotalQuantity = $monthlyOrders->sum(function($order) {
                $produced = $order->orderModel->submodels->flatMap(fn($s) => $s->sewingOutputs)->sum('quantity');
                return max($order->quantity - $produced, 0);
            });

            $monthlyDailyQuantityNeeded = $monthlyDaysToFinish > 0 ? round($monthlyTotalQuantity / $monthlyDaysToFinish) : 0;

            $selectedMonthDate = \Carbon\Carbon::parse($selectedMonth);
            $monthlyDeadline = $selectedMonthDate->endOfMonth();
            $monthlyWorkingDaysUntilDeadline = 0;
            $temp = $today->copy();
            while ($temp->lessThanOrEqualTo($monthlyDeadline)) {
                if (!$temp->isSunday()) $monthlyWorkingDaysUntilDeadline++;
                $temp->addDay();
            }

            $monthlyDeadlineExceeded = $monthlyDaysToFinish > $monthlyWorkingDaysUntilDeadline;
            $monthlyRequiredWorkersForDeadline = null;
            if ($monthlyDeadlineExceeded && $monthlyWorkingDaysUntilDeadline > 0) {
                $requiredMinutes = ceil($monthlyMinutesTotal / $monthlyWorkingDaysUntilDeadline);
                $monthlyRequiredWorkersForDeadline = ceil($requiredMinutes / 500);
            }

            $result[] = [
                'id' => $groupId,
                'name' => $group->name,
                'responsibleUser' => $group->responsibleUser->employee,
                'avgWorkersLast30Days' => round($avgWorkers, 2),
                'dailyProductionMinutes' => round($dailyProductionMinutes, 2),
                'monthlyOrdersCount' => $monthlyOrders->count(),
                'monthlySewingOutputsSum' => $monthlySewingOutputsSum, // <-- Shu oy ichidagi sewingOutputs summasi
                'monthlyMinutesTotal' => $monthlyMinutesTotal,
                'monthlyDaysToFinish' => $monthlyDaysToFinish,
                'monthlyDailyQuantityNeeded' => $monthlyDailyQuantityNeeded,
                'monthlyDeadline' => [
                    'target_date' => $monthlyDeadline->toDateString(),
                    'working_days_until_deadline' => $monthlyWorkingDaysUntilDeadline,
                    'deadline_exceeded' => $monthlyDeadlineExceeded,
                    'required_workers_for_deadline' => $monthlyRequiredWorkersForDeadline,
                ],
            ];
        }

        return response()->json($result);
    }

}