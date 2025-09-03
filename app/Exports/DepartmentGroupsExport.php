<?php

namespace App\Exports;

use App\Models\Group;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class DepartmentGroupsExport implements FromView
{
    protected $departmentId, $startDate, $endDate, $groupId, $orderIds;

    public function __construct($departmentId, $startDate, $endDate, $groupId, $orderIds)
    {
        $this->departmentId = $departmentId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->groupId = $groupId;
        $this->orderIds = $orderIds;
    }

    public function view(): View
    {
        $departmentId = $this->departmentId;
        $startDate = $this->startDate;
        $endDate = $this->endDate;
        $groupId = $this->groupId;
        $orderIds = $this->orderIds ?? [];
        $type = $this->type ?? 'normal'; // agar aup kerak bo‘lsa

        // Guruhlarni olish (xodimlar bilan birga salaryPayments ham yuklaymiz)
        $groupQuery = Group::where('department_id', $departmentId)
            ->with(['employees' => function ($query) use ($type) {
                $query->select('id', 'name', 'position_id', 'group_id', 'salary', 'balance', 'payment_type', 'status')
                    ->with('salaryPayments');

                if ($type === 'aup') {
                    $query->where('type', 'aup');
                } else {
                    $query->where('type', '!=', 'aup');
                }
            }]);

        if (!empty($groupId)) {
            $groupQuery->where('id', $groupId);
        }

        $groups = $groupQuery->get();

        $result = $groups->map(function ($group) use ($startDate, $endDate, $orderIds) {
            $employees = $group->employees
                ->map(function ($employee) use ($startDate, $endDate, $orderIds) {
                    return app('App\Http\Controllers\CasherController')
                        ->getEmployeeEarnings($employee, $startDate, $endDate, $orderIds);
                })
                ->filter();

            $groupTotal = $employees->sum(fn($e) => $e['balance'] ?? 0);

            return [
                'id' => $group->id,
                'name' => $group->name,
                'total_balance' => $groupTotal,
                'employees' => $employees->values()->toArray(),
            ];
        })->values()->toArray();

        // Guruhsiz xodimlarni olish
        $ungroupedEmployees = Employee::where('department_id', $departmentId)
            ->whereNull('group_id')
            ->where('type', $type === 'aup' ? 'aup' : '!=', 'aup')
            ->select('id', 'name', 'group_id', 'position_id', 'balance', 'salary', 'payment_type', 'status')
            ->with('salaryPayments')
            ->get()
            ->map(function ($employee) use ($startDate, $endDate, $orderIds) {
                return app('App\Http\Controllers\CasherController')
                    ->getEmployeeEarnings($employee, $startDate, $endDate, $orderIds);
            })
            ->filter();

        if ($ungroupedEmployees->isNotEmpty()) {
            $ungroupedTotal = $ungroupedEmployees->sum(fn($e) => $e['balance'] ?? 0);

            $result[] = [
                'id' => null,
                'name' => 'Guruhsiz',
                'total_balance' => $ungroupedTotal,
                'employees' => $ungroupedEmployees->values()->toArray(),
            ];
        }

        return view('exports.department_groups', [
            'groups' => $result
        ]);
    }

}
