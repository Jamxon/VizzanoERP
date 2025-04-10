<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetUserResource;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use App\Exports\EmployersExport;
use Maatwebsite\Excel\Facades\Excel;
class UserController extends Controller
{
    public function getProfile()
    {
        $user = Auth::user();

        $employee = Employee::where('id', $user->employee->id)->first();

        $resource = new GetUserResource($employee);

        return response()->json($resource);
    }
}