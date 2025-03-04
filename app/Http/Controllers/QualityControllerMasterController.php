<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class QualityControllerMasterController extends Controller
{
    public function result(): \Illuminate\Http\JsonResponse
    {
        $department = auth()->user()->department;
        $groups = $department->groups;
        $employees = $groups->map(function ($group) {
            return $group->employees;
        })->flatten();

        return response()->json($employees);
    }
}
