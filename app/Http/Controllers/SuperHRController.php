<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperHRController extends Controller
{
    public function employeeStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $fullName = $request->input('full_name');
        $phone = $request->input('phone') ?? null;
        $address = $request->input('address') ?? null;
        $roleId = $request->input('role_id') ?? null;
        $HiringDate = $request->input('hiring_date') ?? now();
        $type = $request->input('type');
        $departmentId = $request->input('department_id') ?? null;
        $groupId = $request->input('group_id') ?? null;
        $paymentType = $request->input('payment_type');
        $salary = $request->input('salary') ?? 0;

        $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'sometimes|string|max:15',
            'address' => 'required|string|max:255',
            'role_id' => 'required|integer|exists:roles,id',
            'hiring_date' => 'required|date',
            'type' => 'required|string|max:50',
            'department_id' => 'sometimes|integer|exists:departments,id',
            'group_id' => 'sometimes|integer|exists:groups,id',
            'payment_type' => 'required|string|max:50',
            'salary' => 'required|numeric|min:0',
            'image' => 'nullable|image|max:20480',
        ]);

        try {
            DB::beginTransaction();

            if ($request->has('image') && $request->file('image')) {
                $image = $request->file('image');
                $imagePath = $image->store('images', 'public');

                $employee = DB::table('employee')->insertGetId([
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'address' => $address,
                    'role_id' => $roleId,
                    'hiring_date' => $HiringDate,
                    'type' => $type,
                    'department_id' => $departmentId,
                    'group_id' => $groupId,
                    'payment_type' => $paymentType,
                    'salary' => $salary,
                    'image_path' => $imagePath,
                ]);
            } else {

                $employee = DB::table('employee')->insertGetId([
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'address' => $address,
                    'role_id' => $roleId,
                    'hiring_date' => $HiringDate,
                    'type' => $type,
                    'department_id' => $departmentId,
                    'group_id' => $groupId,
                    'payment_type' => $paymentType,
                    'salary' => $salary,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Employee created successfully',
                'employee' => $employee,
            ], 201);
        }
        catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create employee: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function employeeUpdate(Request $request): \Illuminate\Http\JsonResponse
    {
        $employeeId = $request->input('employee_id');
        $fullName = $request->input('full_name');
        $phone = $request->input('phone') ?? null;
        $address = $request->input('address') ?? null;
        $roleId = $request->input('role_id');
        $HiringDate = $request->input('hiring_date') ?? now();
        $type = $request->input('type');
        $departmentId = $request->input('department_id') ?? null;
        $groupId = $request->input('group_id') ?? null;
        $paymentType = $request->input('payment_type');
        $salary = $request->input('salary') ?? 0;
        $status = $request->input('status') ?? true;

        $request->validate([
            'employee_id' => 'required|integer|exists:employee,id',
            'full_name' => 'required|string|max:255',
            'phone' => 'sometimes|string|max:15',
            'address' => 'required|string|max:255',
            'role_id' => 'required|integer|exists:roles,id',
            'hiring_date' => 'required|date',
            'type' => 'required|string|max:50',
            'department_id' => 'sometimes|integer|exists:departments,id',
            'group_id' => 'sometimes|integer|exists:groups,id',
            'payment_type' => 'required|string|max:50',
            'salary' => 'required|numeric|min:0',
            'image' => 'nullable|image|max:20480',
            'status' => 'sometimes|boolean',
        ]);

        try {
            DB::beginTransaction();

            if ($request->has('image') && $request->file('image')) {
                $image = $request->file('image');
                $imagePath = $image->store('images', 'public');

                DB::table('employee')->where('id', $employeeId)->update([
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'address' => $address,
                    'role_id' => $roleId,
                    'hiring_date' => $HiringDate,
                    'type' => $type,
                    'department_id' => $departmentId,
                    'group_id' => $groupId,
                    'payment_type' => $paymentType,
                    'salary' => $salary,
                    'image_path' => $imagePath,
                    'status' => $status,
                ]);
            }
            else {
                DB::table('employee')->where('id', $employeeId)->update([
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'address' => $address,
                    'role_id' => $roleId,
                    'hiring_date' => $HiringDate,
                    'type' => $type,
                    'department_id' => $departmentId,
                    'group_id' => $groupId,
                    'payment_type' => $paymentType,
                    'salary' => $salary,
                    'status' => $status
                ]);
            }
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Employee updated successfully',
            ], 200);
    }
        catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update employee: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function employeeDelete(Request $request): \Illuminate\Http\JsonResponse
    {
        $employeeId = $request->input('employee_id');

        $request->validate([
            'employee_id' => 'required|integer|exists:employee,id',
        ]);

        try {
            DB::beginTransaction();

            DB::table('employee')->where('id', $employeeId)->update([
                'status' => false,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Employee deleted successfully',
            ], 200);
        }
        catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete employee: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function employeeReturn(Request $request): \Illuminate\Http\JsonResponse
    {
        $employeeId = $request->input('employee_id');

        $request->validate([
            'employee_id' => 'required|integer|exists:employee,id',
        ]);

        try {
            DB::beginTransaction();

            DB::table('employee')->where('id', $employeeId)->update([
                'status' => true,
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Employee returned successfully',
            ], 200);
        }
        catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to return employee: ' . $e->getMessage(),
            ], 500);
        }
    }
}
