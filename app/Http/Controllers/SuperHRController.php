<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetEmployeeResource;
use App\Models\Department;
use App\Models\Log;
use App\Models\MainDepartment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperHRController extends Controller
{
    public function getEmployees(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $employees = DB::table('employees')
            ->where('branch_id', $user->employee->branch_id)
            ->where('status', 'working')
            ->orderBy('id', 'desc')
            ->paginate(50);

        $resource = GetEmployeeResource::collection($employees);

        return response()->json($resource);
    }

    public function storeEmployees(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string',
            'group_id' => 'nullable|integer|exists:groups,id',
            'position_id' => 'nullable|integer|exists:positions,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'hiring_date' => 'nullable|date',
            'address' => 'nullable|string',
            'passport_number' => 'nullable|string',
            'passport_code' => 'nullable|string',
            'payment_type' => 'nullable|string',
            'comment' => 'nullable|string',
            'type' => 'nullable|string',
            'birthday' => 'nullable|date'
        ]);

        try {
            DB::beginTransaction();

            $username = $this->generateCodeWithBranch(auth()->user()->employee->branch_id);

            $user = DB::table('users')->insert([
                'username' => $username,
                'password' => $this->hashPassword($request->phone),
                'role_id' => $request->role_id,
            ]);

            $employee = DB::table('employees')->insert([
                'name' => $request->name,
                'phone' => $request->phone,
                'group_id' => $request->group_id,
                'position_id' => $request->position_id,
                'department_id' => $request->department_id,
                'hiring_date' => $request->hiring_date,
                'address' => $request->address,
                'passport_number' => $request->passport_number,
                'passport_code' => $request->passport_code,
                'payment_type' => $request->payment_type,
                'comment' => $request->comment,
                'type' => $request->type,
                'birthday' => $request->birthday,
                'branch_id' => auth()->user()->employee->branch_id,
                'user_id' => $user->id,
            ]);

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Yangi xodim qo‘shildi',
                'create',
                null,
                $employee
            );

            return response()->json(['status' => 'success', 'message' => 'Xodim muvaffaqiyatli qo‘shildi', 'employee' => $employee], 201);
        } catch (\Exception $e) {
            DB::rollBack();

             Log::add(
                auth()->user()->id,
                'Xodim qo‘shishda xatolik',
                'error',
                null,
                $e->getMessage()
             );

            return response()->json(['status' => 'error', 'message' => 'Xodimni qo‘shishda xatolik: ' . $e->getMessage()], 500);
        }

    }

    protected function hashPassword($password): string
    {
        $options = ['cost' => 12];
        return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    private function generateCodeWithBranch(int $branchId): string
    {
        $lastUser = User::where('username', 'like', $branchId . '%')
            ->whereHas('employee', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->orderByDesc('id')
            ->first();

        if ($lastUser && preg_match('/^' . $branchId . '(\d{4})$/', $lastUser->code, $matches)) {
            $lastCode = (int) $matches[1];
        } else {
            $lastCode = 999;
        }

        $newCodePart = $lastCode + 1;

        return $branchId . str_pad($newCodePart, 4, '0', STR_PAD_LEFT); // Masalan: 11000 yoki 21001
    }

    public function getRoles(): \Illuminate\Http\JsonResponse
    {
        $roles = DB::table('roles')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($roles, 200);
    }

    public function getAupEmployee(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $employees = DB::table('employees')
            ->where('branch_id', $user->employee->branch_id)
            ->where('status', 'working')
            ->where('type', 'aup')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($employees, 200);
    }

    public function getPositions(): \Illuminate\Http\JsonResponse
    {
        $positions = DB::table('positions')
            ->orderBy('name', 'desc')
            ->get();

        return response()->json($positions, 200);
    }

    public function storePositions(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $position = DB::table('positions')->insert([
                'name' => $request->name,
            ]);

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Yangi lavozim qo‘shildi',
                'create',
                null,
                $position
            );

            return response()->json(['status' => 'success', 'message' => 'Lavozim muvaffaqiyatli qo‘shildi'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lavozimni qo‘shishda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function updatePositions(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $position = DB::table('positions')->where('id', $id)->update([
                'name' => $request->name,
            ]);

            DB::commit();

            Log::add(
                auth()->user()->id,
                'Lavozim yangilandi',
                'edit',
                null,
                $position
            );

            return response()->json(['status' => 'success', 'message' => 'Lavozim muvaffaqiyatli yangilandi'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Lavozimni yangilashda xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function getDepartments(): \Illuminate\Http\JsonResponse
    {
        try {
            $departments = MainDepartment::where('branch_id', auth()->user()->employee->branch_id)
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
        ]);

        try {
            DB::beginTransaction();

            $department = Department::create([
                'name' => $request->name,
                'branch_id' => auth()->user()->employee->branch_id,
                'responsible_user_id' => $request->responsible_user_id ?? null,
                'main_department_id' => $request->main_department_id ?? null,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'break_time' => $request->break_time,
            ]);

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
            'main_department_id' => 'sometimes|integer|exists:main_department,id',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'break_time' => 'sometimes|integer|min:0',
        ]);

        try {
            DB::beginTransaction();

            $department = Department::findOrFail($id);
            $oldData = $department->toArray();
            $department->update([
                'name' => $request->name ?? $department->name,
                'responsible_user_id' => $request->responsible_user_id ?? $department->responsible_user_id,
                'main_department_id' => $request->main_department_id ?? $department->main_department_id,
                'start_time' => $request->start_time ?? $department->start_time,
                'end_time' => $request->end_time ?? $department->end_time,
                'break_time' => $request->break_time ?? $department->break_time,
                'branch_id' => auth()->user()->employee->branch_id,
            ]);

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