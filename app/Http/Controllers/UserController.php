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

    public function getUserRole()
    {
        // Foydalanuvchining autentifikatsiyasi amalga oshirilganini tekshirish
        if (Auth::check()) {
            // Foydalanuvchining rolini olish
            $role = Auth::user()->role->name; // role ishtirokchi bo'lishi kerak, ya'ni role_id -> role jadvalida

            return response()->json(['role' => $role]);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }
    public function adminCreate(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'username' => 'required',
            'phone' => 'required',
            'password' => 'required',
            'role_id' => 'required',
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'status' => 'active',
            'role_id' => $request->role_id,
        ]);

        $employee = Employee::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'user_id' => $user->id,
            'group_id' => $request->group_id ?? null,
            'payment_type' => 'monthly',
            'salary' => $request->salary ?? 0,
            'hiring_date' => date('Y-m-d'),
        ]);

        if ($user && $employee) {
            return response()->json([
                'message' => 'Admin created successfully',
                'user' => $user,
                'employee' => $employee,
            ]);
        } else {
            return response()->json([
                'message' => 'Admin creation failed',
                'error' => $user->errors() ?? $employee->errors(),
            ], 500);
        }

    }
    public function simpleEmployeeCreate(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'phone' => 'required',
            'payment_type' => 'required',
            'salary' => 'required',
            'hiring_date' => 'required',
        ]);

        $employee = Employee::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'group_id' => $request->group_id ?? null,
            'user_id' => $request->user_id ?? null,
            'payment_type' => $request->payment_type,
            'salary' => $request->salary,
            'hiring_date' => $request->hiring_date,
        ]);

        if ($employee) {
            return response()->json([
                'message' => 'Employee created successfully',
                'employee' => $employee,
            ]);
        } else {
            return response()->json([
                'message' => 'Employee creation failed',
                'error' => $employee->errors(),
            ], 500);
        }
    }

    public function getUsers()
    {
        $users = User::where('role_id', '=', 8)
            ->whereHas('employee', function ($query) {
                $query->where('status', 'working');
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

    public function upgradeToAdmin(Request $request)
    {
        $request->validate([
            'employee_id' => 'required',
            'username' => 'required',
            'password' => 'required',
            'role_id' => 'required',
        ]);

        $employee = Employee::find($request->employee_id);

        $user = User::create([
            'name' => $employee->name,
            'username' => $request->username,
            'phone' => $employee->phone,
            'password' => Hash::make($request->password),
            'status' => 'active',
            'role_id' => $request->role_id,
        ]);

        $employee->user_id = $user->id;
        $employee->save();

        if ($user && $employee) {
            return response()->json([
                'message' => 'User upgraded to admin successfully',
                'user' => $user,
                'employee' => $employee,
            ]);
        } else {
            return response()->json([
                'message' => 'User upgrade failed',
                'error' => $user->errors() ?? $employee->errors(),
            ], 500);
        }
    }

    public function simpleEmployeeUpdate(Request $request)
    {
        $request->validate([
            'employee_id' => 'required',
            'name' => 'required',
            'phone' => 'required',
            'payment_type' => 'required',
            'salary' => 'required',
            'hiring_date' => 'required',
        ]);

        $employee = Employee::find($request->employee_id);

        $employee->name = $request->name;
        $employee->phone = $request->phone;
        $employee->group_id = $request->group_id ?? null;
        $employee->payment_type = $request->payment_type;
        $employee->salary = $request->salary;
        $employee->hiring_date = $request->hiring_date;
        $employee->save();

        if ($employee) {
            return response()->json([
                'message' => 'Employee updated successfully',
                'employee' => $employee,
            ]);
        } else {
            return response()->json([
                'message' => 'Employee update failed',
                'error' => $employee->errors(),
            ], 500);
        }
    }

    public function simpleEmployeeDelete(Request $request)
    {
        $request->validate([
            'employee_id' => 'required',
        ]);

        $employee = Employee::find($request->employee_id);

        if ($employee) {
            $employee->status = 'inactive';
            $user = User::where('id', $employee->user_id)->first();
            if ($user) {
                $user->status = 'inactive';
                if ($user->save() && $employee->save()) {
                    return response()->json([
                        'message' => 'Employee and User deleted successfully',
                    ]);
                } else {
                    return response()->json([
                        'message' => 'Employee or User deletion failed',
                        'error' => $employee->errors() ?? $user->errors(),
                    ], 500);
                }
            }
            $employee->save();
            return response()->json([
                'message' => 'Employee deleted successfully',
            ]);
        } else {
            return response()->json([
                'message' => 'Employee deletion failed',
                'error' => $employee->errors(),
            ], 500);
        }
    }

    public function simpleEmployeeShow(Request $request)
    {
        $request->validate([
            'employee_id' => 'required',
        ]);

        $employee = Employee::find($request->employee_id);

        if ($employee) {
            return response()->json([
                'employee' => $employee,
            ]);
        } else {
            return response()->json([
                'message' => 'Employee not found',
            ], 404);
        }
    }

    public function simpleEmployeeSearch(Request $request)
    {
        $employees = Employee::where('name', 'like', '%' . $request . '%')
            ->orWhere('phone', 'like', '%' . $request . '%')
            ->get();

        return response()->json([
            'employees' => $employees,
        ]);
    }

    public function simpleEmployeeSearchByGroup(Request $request)
    {
        $request->validate([
            'group_id' => 'required',
        ]);

        $employees = Employee::where('group_id', $request->group_id)->get();

        return response()->json([
            'employees' => $employees,
        ]);
    }

    public function simpleEmployeeSearchByDepartment(Request $request)
    {
        $request->validate([
            'department_id' => 'required',
        ]);

        $employees = Employee::whereHas('group', function ($query) use ($request) {
            $query->where('department_id', $request->department_id);
        })->get();

        return response()->json([
            'employees' => $employees,
        ]);
    }
}
