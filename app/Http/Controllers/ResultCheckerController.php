<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetGroupsForResultCheckerResource;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ResultCheckerController extends Controller
{

    public function getGroups(Request $request): \Illuminate\Http\JsonResponse
    {
        $groups = Group::where('department_id', $request->input('department_id'))
            ->with([
                'responsibleUser',
                'orders.order',
                'orders.orderSubmodel.submodel',
                'orders.orderSubmodel.orderModel.model',
                'orders.orderSubmodel.sewingOutputs' => function ($q) {
                    $q->whereDate('created_at', now());
                },
            ])
            ->get();

        return response()->json(GetGroupsForResultCheckerResource::collection($groups));
    }

    public function getEmployeeByGroupId(Request $request): \Illuminate\Http\JsonResponse
    {
        $group = Group::with([
            'employees.employeeResults' => function ($query) {
                $query->whereDate('created_at', Carbon::today())
                    ->with(['time', 'tarification', 'createdBy.employee']); // relationlar ichkaridan yuklanadi
            }
        ])
            ->find($request->input('group_id'));

        return response()->json($group?->employees);
    }

}