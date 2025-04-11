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
    public function getAupEmployee(): \Illuminate\Http\JsonResponse
    {
        $user = auth()->user();
        $employees = DB::table('employees')
            ->where('branch_id', $user->employee->branch_id)
            ->where('status', 'working')
            ->where('type', 'aup')
            ->orderBy('updated_at', 'desc')
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