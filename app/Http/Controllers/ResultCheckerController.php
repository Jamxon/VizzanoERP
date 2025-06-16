<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetGroupsForResultCheckerResource;
use App\Models\Group;
use Illuminate\Http\Request;

class ResultCheckerController extends Controller
{
    public function getGroups(Request $request): \Illuminate\Http\JsonResponse
    {
        $groups = Group::where('department_id', $request->input('department_id'))
            ->with([
                'responsibleUser',
                'orders.orderSubmodel.submodel',
                'orders.order',
                'orders.orderSubmodel.sewingOutputs'
            ])
            ->get();



        $resource = GetGroupsForResultCheckerResource::collection($groups);

        return response()->json($resource);
    }

}