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
                'orders.orderSubmodel.submodel',
                'orders.order' => function ($q) {
                    $q->whereIn('status', ['tailoring', 'pending', 'cutting']);
                },
                'orders.orderSubmodel.sewingOutputs'
            ])
            ->get();



        $resource = GetGroupsForResultCheckerResource::collection($groups);

        return response()->json($resource);
    }

}