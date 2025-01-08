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

    public function export()
    {
        return Excel::download(new EmployersExport, 'employers.xlsx');
    }
    public function getUsersMaster()
    {
        $users = User::where('role_id', '=', 8)
            ->whereHas('employee', function ($query) {
                $query->where('status', 'working');
                $query->where('branch_id', '=', Auth::user()->employee->branch_id);
            })
            ->get();

        if ($users) {
            return response()->json($users);
        } else {
            return response()->json([
                'message' => 'Users not found',
            ], 404);
        }
    }

    public function getUsersSubMaster()
    {
        $users = User::where('role_id', '=', 9)
            ->whereHas('employee', function ($query) {
                $query->where('status', 'working');
                $query->where('branch_id', '=', Auth::user()->employee->branch_id);
            })
            ->get();

        if ($users) {
            return response()->json($users);
        } else {
            return response()->json([
                'message' => 'Users not found',
            ], 404);
        }
    }
}