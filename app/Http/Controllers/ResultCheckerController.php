<?php

namespace App\Http\Controllers;

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

        return response()->json($groups);
    }

}