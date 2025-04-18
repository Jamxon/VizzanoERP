<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\OrderGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GroupController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        $branch = $user->employee->branch;

        if (!$branch) {
            return response()->json(['message' => 'Branch not found for this user'], 404);
        }

        $departments = $branch->departments;

        $groups = Group::whereIn('department_id', $departments->pluck('id'))->get();

        return response()->json($groups, 200);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $group = Group::create($request->all());
        return response()->json($group);
    }

    public function update(Request $request, Group $group): \Illuminate\Http\JsonResponse
    {
        $groups = Group::find($group->id);

        $groups->update($request->all());

        return response()->json([
            'message' => 'Group updated successfully',
            'group' => $group,
        ], 200);
    }


    public function delete(Group $group): \Illuminate\Http\JsonResponse
    {
       if ($group->delete()){
              return response()->json([
                'message' => 'Group deleted successfully'
              ], 200);
       }
       else{
              return response()->json([
                'message' => 'Group not found'
              ], 404);
       }
    }
}