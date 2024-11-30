<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetEmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function getEmployees()
    {
        $employees = Employee::where('status', 'active')->get();

        $resource =  GetEmployeeResource::collection($employees);

        return response()->json([
            'employees' => $resource,
        ]);
    }
}
