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
        $groupQuery = Group::where('department_id', $this->departmentId)
            ->with(['employees' => function ($query) {
                $query->select('id', 'name', 'position_id', 'group_id', 'balance', 'payment_type', 'status')
                    ->with('salaryPayments');
            }]);

        if (!empty($this->groupId)) {
            $groupQuery->where('id', $this->groupId);
        }

        $groups = $groupQuery->get();

        $result = $groups->map(function ($group) {
            $employees = $group->employees
                ->map(function ($employee) {
                    return app('App\Http\Controllers\CasherController')
                        ->getEmployeeEarnings($employee, $this->startDate, $this->endDate, $this->orderIds);
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

        return view('exports.department_groups', [
            'groups' => $result
        ]);
    }
}
