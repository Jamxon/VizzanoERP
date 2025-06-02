<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\OrderGroup;
use App\Models\Department;
use App\Models\OrderSubModel;
use App\Models\Order;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;


class GroupController extends Controller
{
    public function orderGroupStore(Request $request)
    {
        try {
            $submodelId = $request->submodel_id;
            $orderId = $request->order_id;
            $groupId = $request->group_id;
            $number = $request->number;

            // Mavjudligini submodel_id orqali tekshiramiz
            $existing = OrderGroup::where('submodel_id', $submodelId)->first();

            if ($existing) {
                // Yangilaymiz
                $existing->update([
                    'order_id' => $orderId,
                    'group_id' => $groupId,
                    'number' => $number
                ]);

                Log::add(
                auth()->user()->id,
                "Guruh plani o'zgartirildi!",
                "edit",
                null,
                [
                    "Group" => Group::where("id", $groupId)->first()->name,
                    "Submodel" => OrderSubModel::where("id", $submodelId)->first()->submodel?->name,
                    "Order" => Order::where("id", $orderId)->first()->name
                ]    
                );
            } else {
                // Yangi yozuv yaratamiz
                OrderGroup::create([
                    'order_id' => $orderId,
                    'group_id' => $groupId,
                    'submodel_id' => $submodelId,
                    'number' => $number
                ]);

                Log::add(
                auth()->user()->id,
                'Guruh plani shakllantirildi!',
                "create",
                null,
                [
                    "Group" => Group::where("id", $groupId)->first()->name,
                    "Submodel" => OrderSubModel::where("id", $submodelId)->first()->submodel?->name,
                    "Order" => Order::where("id", $orderId)->first()->name
                ]);
            }

            return response()->json([
                'message' => 'Success'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Xatolik yuz berdi!',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getGroupsWithPlan(Request $request)
    {
        $departments = Department::where("id", $request->department_id)
            ->with([
                "groups.orders.order",
                "groups.orders.orderSubmodel.submodel",
                "groups.orders.orderSubmodel.submodel.model",
                "groups.orders.orderSubmodel.sewingOutputs:id,order_submodel_id,quantity", // faqat kerakli maydonlar
                "groups.responsibleUser.employee"
            ])
            ->first();

        if (!$departments) {
            return response()->json(['message' => 'Department topilmadi.'], 404);
        }

        // Har bir group ichidagi orders ni map qilib chiqamiz
        $departments->groups->each(function ($group) {
            $group->orders->each(function ($orderGroupItem) {
                if ($orderGroupItem->orderSubmodel) {
                    $sewingQuantity = $orderGroupItem->orderSubmodel->sewingOutputs->sum('quantity');
                    unset($orderGroupItem->orderSubmodel->sewingOutputs); // sewing_outputs ni olib tashlaymiz
                    $orderGroupItem->orderSubmodel->sewing_quantity = $sewingQuantity;
                } else {
                    $orderGroupItem->orderSubmodel = (object)[
                        'sewing_quantity' => 0
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