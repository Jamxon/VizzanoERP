<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\QualityCheck;
use Illuminate\Http\Request;

class QualityControllerMasterController extends Controller
{
    public function result()
    {
        $department = Department::where('responsible_user_id', auth()->id())->first();
        $groups = $department->groups;
        $employees = $groups->map(function ($group) {
            return $group->employees->map(function ($employee) {;
                return $employee->user;
            });
        })->flatten();

        $qualityChecks = QualityCheck::whereIn('user_id', $employees->pluck('id'))
            ->whereDate('created_at', now())
            ->orderBy('order_sub_model_id', 'ASC')
            ->with('orderSubModel')
            ->get();

        return response()->json($qualityChecks);
    }
}
