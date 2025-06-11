<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\OrderGroup;
use App\Models\Department;
use App\Models\OrderSubModel;
use App\Models\Order;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;


class GroupController extends Controller
{
    public function orderGroupStore(Request $request)
    {
        try {
            $submodelId = $request->submodel_id;
            $orderId = $request->order_id;
            $groupId = $request->group_id;

            // Mavjudligini submodel_id orqali tekshiramiz
            $existing = OrderGroup::where('submodel_id', $submodelId)->first();

            if ($existing) {
                // Yangilaymiz
                $existing->update([
                    'order_id' => $orderId,
                    'group_id' => $groupId
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
                "groups.orders.orderSubmodel.submodelSpend",
                "groups.orders.orderSubmodel.sewingOutputs",
                "groups.employees.attendances:id,employee_id,date",
            ])
            ->first();

        if (!$departments) {
            return response()->json(['message' => 'Department topilmadi.'], 404);
        }

        $excludedStatuses = ['completed', 'checking', 'checked', 'packaging', 'packaged'];

        $departments->groups->each(function ($group) use ($excludedStatuses) {
            $pastWeek = now()->subDays(7);
            $attendances = collect();

            foreach ($group->employees as $employee) {
                $weeklyAttendances = $employee->attendances
                    ->where('date', '>=', $pastWeek)
                    ->filter(fn($a) => Carbon::parse($a->date)->dayOfWeek !== Carbon::SUNDAY);

                $attendances = $attendances->merge($weeklyAttendances);
            }

            // Har bir employee_id + date bo‘yicha noyob davomatlar
            $uniqueAttendances = $attendances->unique(fn($item) => $item->employee_id . '_' . $item->date);
            $days = $uniqueAttendances->pluck('date')->unique()->count();
            $employeeCount = $group->employees->count();

            // Yangi, aniq o‘rtacha hisoblash
            $avgAttendance = floor(($days > 0 && $employeeCount > 0)
                ? $uniqueAttendances->count() / $days
                : 0);

            // Ish vaqtini hisoblash
            $start = Carbon::parse($group->department->start_time);
            $end = Carbon::parse($group->department->end_time);
            $break = $group->department->break_time ?? 0;
            $workMinutes = $end->diffInMinutes($start) - $break;
            $workSeconds = $workMinutes * 60;

            $totalWorkSeconds = $workSeconds * $avgAttendance;

            // Order statuslar bo‘yicha filtr
            $filteredOrders = $group->orders->filter(function ($orderGroupItem) use ($excludedStatuses) {
                return $orderGroupItem->order && !in_array($orderGroupItem->order->status, $excludedStatuses);
            })->values();

            $group->setRelation('orders', $filteredOrders);

            // Har bir orderSubmodel bo‘yicha plan hisoblash
            $group->orders->each(function ($orderGroupItem) use ($totalWorkSeconds, $avgAttendance) {
                if ($orderGroupItem->orderSubmodel) {
                    $submodel = $orderGroupItem->orderSubmodel;
                    $sewingQuantity = $submodel->sewingOutputs->sum('quantity');
                    $quantity = $submodel->orderModel->order->quantity ?? 0;
                    $remaining = max(0, $quantity - $sewingQuantity);
                    unset($submodel->sewingOutputs);
                    unset($submodel->orderModel);

                    $submodel->sewing_quantity = $sewingQuantity;
                    $submodel->remaining_quantity = $remaining;

                    $spends = $submodel->submodelSpend ?? collect();

                    $spends->groupBy('region')->each(function ($spendGroup, $region) use ($avgAttendance, $submodel, $totalWorkSeconds, $remaining) {
                        $spendSeconds = $spendGroup->sum('seconds');
                        $averagePlan = $spendSeconds > 0 ? floor($totalWorkSeconds / $spendSeconds) : 0;
                        $finalPlan = $remaining / $averagePlan ?? 1;
                        $submodel->{"plan_$region"} = $finalPlan;
                        $submodel->avaragePlan = $averagePlan;
                        $submodel->perEmployee = $averagePlan / $avgAttendance;
                    });

                    $submodel->avgAttendance = round($avgAttendance, 2);
                } else {
                    $orderGroupItem->orderSubmodel = (object)[
                        'sewing_quantity' => 0,
                        'remaining_quantity' => 0,
                        'plan_uz' => 0,
                        'plan_ru' => 0,
                    ];
                }
            });

            // 🔴 Eslatma: employees va department ni chiqarmaslik
            unset($group->employees);
            unset($group->department);
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