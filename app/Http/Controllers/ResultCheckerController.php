<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetGroupsForResultCheckerResource;
use App\Models\Group;
use App\Models\OrderGroup;
use App\Models\OrderSubModel;
use App\Models\SewingOutputs;
use Illuminate\Http\Request;

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

}