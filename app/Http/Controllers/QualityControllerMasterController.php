<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\OrderSubModel;
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

//        $qualityChecks = QualityCheck::whereIn('user_id', $employees->pluck('id'))
//            ->whereDate('created_at', now())
//            ->orderBy('order_sub_model_id', 'ASC')
//            ->with('order_sub_model.submodel')
//            ->get();

        $orderSubModel = OrderSubModel::whereHas('qualityChecks' , function($query) use ($employees) {
            $query->whereIn('user_id', $employees->pluck('id'));
            $query->whereDate('created_at', now());
            $query->with('qualityCheck','submodel');
        })->get();

        return response()->json($orderSubModel);
    }
}
