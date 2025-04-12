<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Http\Request;

class EmployeeExport implements FromView
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function view(): View
    {
        $filters = $this->request->only(['search', 'department_id', 'group_id', 'status', 'role_id']);
        $user = auth()->user();

        $query = Employee::with('user.role', 'position', 'group', 'department')
            ->where('branch_id', $user->employee->branch_id);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhereHas('position', fn($q) => $q->where('name', 'like', "%$search%"))
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('username', 'like', "%$search%")
                            ->orWhereHas('role', fn($q) => $q->where('description', 'like', "%$search%"));
                    });
            });
        }

        $query->when($filters['department_id'] ?? false, fn($q) => $q->where('department_id', $filters['department_id']))
            ->when($filters['group_id'] ?? false, fn($q) => $q->where('group_id', $filters['group_id']))
            ->when($filters['status'] ?? false, fn($q) => $q->where('status', $filters['status']))
            ->when($filters['role_id'] ?? false, function ($q) use ($filters) {
                $q->whereHas('user', fn($q) => $q->where('role_id', $filters['role_id']));
            });

        $employees = $query->orderByDesc('updated_at')->get();

        return view('exports.employees', [
            'employees' => $employees
        ]);
    }
}
