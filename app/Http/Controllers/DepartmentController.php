<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Group;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $departments = Department::where('branch_id', $user->branch_id)->get();

        return response()->json($departments);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'responsible_user_id' => 'required|integer|exists:users,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'groups' => 'required|array',
        ]);

        $department = Department::create([
            'name' => $data['name'],
            'responsible_user_id' => $data['responsible_user_id'],
            'branch_id' => $data['branch_id'],
        ]);

        foreach ($data['groups'] as $group) {
            Group::create([
                'name' => $group['name'],
                'responsible_user_id' => $group['responsible_user_id'],
                'department_id' => $department->id,
            ]);
        }

        return response()->json($department);
    }

    public function update(Request $request, Department $department)
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

    public function destroy(Department $department)
    {
        $department->delete();

        return response()->json(['message' => 'Department deleted']);
    }
}
