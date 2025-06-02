<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\OrderGroup;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;


class GroupController extends Controller
{
    public function getGroupsWithPlan(Request $request)
{
    $departments = Department::where("id", $request->department_id)
        ->with([
            "groups.orders.order",
            "groups.orders.orderSubmodel.submodel",
            "groups.orders.orderSubmodel.submodel.model",
            "groups.responsibleUser.employee",
            "groups.orders.orderSubmodel.sewingOutputs" // bu yerda sewingOutputs ni ham chaqiramiz
        ])
        ->first();

    if (!$departments) {
        return response()->json(['message' => 'Department topilmadi.'], 404);
    }

    // Har bir group ichidagi orders ni map qilib chiqamiz
    $departments->groups->each(function ($group) {
        $group->orders->each(function ($orderGroupItem) {
            if ($orderGroupItem->orderSubmodel) {
                $orderGroupItem->orderSubmodel->total_quantity = $orderGroupItem->orderSubmodel->sewingOutputs->sum('quantity');
            } else {
                $orderGroupItem->orderSubmodel = (object)[
                    'total_quantity' => 0
                ];
            }
        });
    });

    return response()->json($departments, 200);
}
   

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

    public function show(Group $group): \Illuminate\Http\JsonResponse
    {
        return response()->json($group, 200);
    }

    public function update(Request $request, Group $group): \Illuminate\Http\JsonResponse
    {
        try {
            $group->update([
                'name' => $request->name ?? $group->name,
                'department_id' => $request->department_id ?? $group->department_id,
                'responsible_user_id' => $request->responsible_user_id ?? $group->responsible_user_id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
        return response()->json([
            'message' => 'Group updated successfully',
            'group' => $group
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