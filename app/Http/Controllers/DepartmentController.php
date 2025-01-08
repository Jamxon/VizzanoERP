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
            'groups' => 'nullable|array',
        ]);

        $user = User::find($data['responsible_user_id']);

        $department = Department::create([
            'name' => $data['name'],
            'responsible_user_id' => $data['responsible_user_id'],
            'branch_id' => auth()->user()->employee->branch_id,
        ]);

        $user->employee->update([
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
        $data = $request->validate([
            'name' => 'required|string',
            'responsible_user_id' => 'required|integer|exists:users,id',
            'groups' => 'nullable|array',
        ]);

        $department = Department::findOrFail($id); // Mavjud departmentni topish
        $department->update([
            'name' => $data['name'],
            'responsible_user_id' => $data['responsible_user_id'],
        ]);

        $user = User::find($data['responsible_user_id']);
        $user->employee->update([
            'group_id' => null,
            'department_id' => $department->id,
        ]);

        // Avvalgi guruhlarni o‘chirib tashlash yoki yangilash uchun
        $existingGroupIds = [];
        foreach ($data['groups'] as $group) {
            if (isset($group['id'])) {
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
                $newUser->employee->update([
                    'group_id' =>  null,
                    'department_id' => $department->id,
                ]);

                $existingGroupIds[] = $newGroup->id;
            }
        }

        // Mavjud bo'lmagan guruhlarni o'chirish
        Group::where('department_id', $department->id)
            ->whereNotIn('id', $existingGroupIds)
            ->delete();

        return response()->json($department);
    }

    public function destroy(Department $department): \Illuminate\Http\JsonResponse
    {
        $department->delete();

        return response()->json(['message' => 'Department deleted']);
    }
}
