<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Log;
use App\Models\MainDepartment;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperHRController extends Controller
{
    public function employeeStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

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

            $employeeData = $request->only(['full_name', 'phone', 'address', 'role_id', 'hiring_date', 'type', 'department_id', 'group_id', 'payment_type', 'salary']);

            if ($request->hasFile('image')) {
                $employeeData['image_path'] = $request->file('image')->store('images', 'public');
            }

            $employeeId = DB::table('employee')->insertGetId($employeeData);

            Log::add(
                $user->id,
                'Yangi xodim qo‘shildi',
                'create',
                null,
                $employeeData
            );

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Xodim muvaffaqiyatli qo‘shildi', 'employee_id' => $employeeId], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Xodimni qo‘shishda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function employeeUpdate(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

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
            'status' => 'sometimes|boolean',
        ]);

        try {
            DB::beginTransaction();

            $employee = DB::table('employee')->where('id', $request->employee_id)->first();
            $oldData = (array) $employee;

            $newData = $request->only(['full_name', 'phone', 'address', 'role_id', 'hiring_date', 'type', 'department_id', 'group_id', 'payment_type', 'salary', 'status']);

            DB::table('employee')->where('id', $request->employee_id)->update($newData);

            Log::add(
                $user->id,
                'Xodim yangilandi',
                'edit',
                $oldData,
                $newData
            );

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Xodim muvaffaqiyatli yangilandi'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Xodimni yangilashda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function employeeDelete(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        $request->validate(['employee_id' => 'required|integer|exists:employee,id']);

        try {
            DB::beginTransaction();
            $employee = DB::table('employee')->where('id', $request->employee_id)->first();
            DB::table('employee')->where('id', $request->employee_id)->update(['status' => false]);
            Log::add(
                $user->id,
                'Xodim o‘chirildi',
                'delete',
                (array) $employee,
                null
            );

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Xodim muvaffaqiyatli o‘chirildi'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Xodimni o‘chirishda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function employeeReturn(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();

        $request->validate(['employee_id' => 'required|integer|exists:employee,id']);

        try {
            DB::beginTransaction();
            $employee = DB::table('employee')->where('id', $request->employee_id)->first();
            DB::table('employee')->where('id', $request->employee_id)->update(['status' => true]);
            Log::add(
                $user->id,
                'Xodim qayta tiklandi',
                'return',
                null,
                (array) $employee
            );

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Xodim qayta tiklandi'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Xodimni qayta tiklashda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function getRoles(): \Illuminate\Http\JsonResponse
    {
        try {
            $roles = Role::all();
            return response()->json($roles, 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Rollarni olishda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function storeRoles(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'task' => 'nullable|text',
        ]);

        try {
            DB::beginTransaction();

            // PostgreSQL uchun substring orqali sonni ajratib olish
            $lastRole = DB::table('roles')
                ->where('name', 'like', 'role%')
                ->orderByRaw("CAST(REGEXP_REPLACE(name, '\\D', '', 'g') AS INTEGER) DESC")
                ->first();

            $lastNumber = 0;
            if ($lastRole && preg_match('/role(\d+)/', $lastRole->name, $matches)) {
                $lastNumber = (int)$matches[1];
            }

            $newRoleName = 'role' . ($lastNumber + 1);

            $role = Role::create([
                'name' => $newRoleName,
                'description' => $request->description,
                'task' => $request->task ?? null,
            ]);

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Yangi rol qo‘shildi',
                'create',
                null,
                $role->toArray()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Rol muvaffaqiyatli qo‘shildi',
                'role' => $role
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Rolni qo‘shishda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateRoles(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'task' => 'nullable|text',
        ]);

        try {
            DB::beginTransaction();

            $role = Role::findOrFail($id);
            $role->update([
                'name' => $role->name,
                'description' => $request->description,
                'task' => $request->task ?? $role->task,
            ]);

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Rol yangilandi',
                'edit',
                $role->toArray(),
                $role->toArray()
            );

            return response()->json(['status' => 'success', 'message' => 'Rol muvaffaqiyatli yangilandi', 'role' => $role], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Rolni yangilashda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function getDepartments(): \Illuminate\Http\JsonResponse
    {
        try {
            $departments = MainDepartment::where('branch_id', auth()->user()->branch_id)
                ->with('departments')
                ->get();
            return response()->json($departments, 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Bo‘limlarni olishda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function storeDepartments(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'required|integer|exists:branches,id',
        ]);

        try {
            DB::beginTransaction();

            $department = Department::create(
                $request->only([
                    'name',
                    'responsible_user_id',
                    'main_department_id',
                    'start_time',
                    'end_time',
                    'break_time'
                ])
            );

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Yangi bo‘lim qo‘shildi',
                'create',
                null,
                $department->toArray()
            );

            return response()->json(['status' => 'success', 'message' => 'Bo‘lim muvaffaqiyatli qo‘shildi', 'department' => $department], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Bo‘limni qo‘shishda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function updateDepartments(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'responsible_user_id' => 'sometimes|integer|exists:users,id',
            'main_department_id' => 'sometimes|integer|exists:main_departments,id',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'break_time' => 'sometimes|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $department = Department::findOrFail($id);
            $oldData = $department->toArray();
            $department->update($request->only([
                'name',
                'responsible_user_id',
                'main_department_id',
                'start_time',
                'end_time',
                'break_time'
            ]));

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Bo‘lim yangilandi',
                'edit',
                $oldData,
                $department->toArray()
            );

            return response()->json(['status' => 'success', 'message' => 'Bo‘lim muvaffaqiyatli yangilandi', 'department' => $department], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Bo‘limni yangilashda xatolik: ' . $e->getMessage()], 500);
        }
    }
}