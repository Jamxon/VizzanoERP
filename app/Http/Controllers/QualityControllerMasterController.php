<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class QualityControllerMasterController extends Controller
{
    public function result()
    {
      return   $department = Department::where('responsible_user_id', auth()->id())->first();
        $groups = $department->groups;
        $employees = $groups->map(function ($group) {
            return $group->employees;
        })->flatten();

        return response()->json($employees);
    }
}
