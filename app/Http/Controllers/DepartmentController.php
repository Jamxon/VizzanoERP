<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        $departments = Department::where('branch_id', $user->employee->branch_id)
            ->with('responsibleUser','groups.responsibleUser')
            ->get();

        return response()->json($departments);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'responsible_user_id' => 'required|integer|exists:users,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'groups' => 'required|array',
        ]);

        $user = User::find($data['responsible_user_id']);

        $department = Department::create([
            'name' => $data['name'],
            'responsible_user_id' => $data['responsible_user_id'],
            'branch_id' => $data['branch_id'],
        ]);

        $user->employee->update([
            'group_id' => null,
            'department_id' => $department->id,
        ]);

        foreach ($data['groups'] as $group) {
            Group::create([
                'name' => $group['name'],
                'responsible_user_id' => $group['responsible_user_id'],
                'department_id' => $department->id,
            ]);

            $user = User::find($group['responsible_user_id']);
            $user->employee->update([
                'group_id' => null,
                'department_id' => $department->id,
            ]);
        }

        return response()->json($department);
    }

    public function update(Request $request, Department $department): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'responsible_user_id' => 'required|integer|exists:users,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'groups' => 'required|array',
        ]);

        $department->update([
            'name' => $data['name'],
            'responsible_user_id' => $data['responsible_user_id'],
            'branch_id' => $data['branch_id'],
        ]);

        $department->groups()->delete();

        foreach ($data['groups'] as $group) {
            Group::create([
                'name' => $group['name'],
                'responsible_user_id' => $group['responsible_user_id'],
                'department_id' => $department->id,
            ]);
        }

        return response()->json($department);
    }

    public function destroy(Department $department): \Illuminate\Http\JsonResponse
    {
        $department->delete();

        return response()->json(['message' => 'Department deleted']);
    }
}
