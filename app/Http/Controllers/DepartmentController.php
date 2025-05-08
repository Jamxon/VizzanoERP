<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
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
            'groups' => 'nullable|array',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'break_time' => 'nullable|integer',
        ]);

        $user = User::find($data['responsible_user_id']);

        $department = Department::create([
            'name' => $data['name'],
            'responsible_user_id' => $data['responsible_user_id'],
            'branch_id' => auth()->user()->employee->branch_id,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'break_time' => $data['break_time'],
        ]);

        $employee = Employee::find($user->employee->id);

        $employee->update([
            'group_id' => null,
            'department_id' => $department->id,
        ]);

       if ($department){
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
       }

        return response()->json($department);
    }

    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->merge([
            'start_time' => $request->start_time ? date("H:i", strtotime($request->start_time)) : null,
            'end_time' => $request->end_time ? date("H:i", strtotime($request->end_time)) : null,
        ]);

        $data = $request->validate([
            'name' => 'required|string',
            'responsible_user_id' => 'required|integer|exists:users,id',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'break_time' => 'nullable|integer',
            'groups' => 'nullable|array',
            'groups.*.id' => 'nullable|integer|exists:groups,id',
            'groups.*.name' => 'required|string',
            'groups.*.responsible_user_id' => 'required|integer|exists:users,id',
        ]);


        $department = Department::findOrFail($id);

        $department->update([
            'name' => $data['name'],
            'responsible_user_id' => $data['responsible_user_id'],
            'start_time' => $data['start_time'] ?? $department->start_time,
            'end_time' => $data['end_time'] ?? $department->end_time,
            'break_time' => $data['break_time'] ?? $department->break_time,
        ]);

        $user = User::find($data['responsible_user_id']);
        if ($user && $user->employee) {
            $user->employee->update([
                'group_id' => null,
                'department_id' => $department->id,
            ]);
        }

        $existingGroupIds = [];
        $data['groups'] = $data['groups'] ?? [];

        foreach ($data['groups'] as $group) {
            if (!empty($group['id'])) {
                $existingGroup = Group::find($group['id']);
                if ($existingGroup) {
                    $existingGroup->update([
                        'name' => $group['name'],
                        'responsible_user_id' => $group['responsible_user_id'],
                    ]);
                    $existingGroupIds[] = $existingGroup->id;
                }
            } else {
                $newGroup = Group::create([
                    'name' => $group['name'],
                    'responsible_user_id' => $group['responsible_user_id'],
                    'department_id' => $department->id,
                ]);

                $newUser = User::find($group['responsible_user_id']);
                if ($newUser && $newUser->employee) {
                    $newUser->employee->update([
                        'group_id' => null,
                        'department_id' => $department->id,
                    ]);
                }

                $existingGroupIds[] = $newGroup->id;
            }
        }

        Group::where('department_id', $department->id)
            ->whereNotIn('id', $existingGroupIds)
            ->delete();

        return response()->json([
            'message' => 'Department updated successfully',
            'department' => $department->load('groups'),
        ]);
    }

    public function destroy(Department $department): \Illuminate\Http\JsonResponse
    {
        $department->delete();

        return response()->json(['message' => 'Department deleted']);
    }
}
