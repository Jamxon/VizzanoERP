<?php

namespace App\Http\Controllers;

use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperHRController extends Controller
{
    private function logAction($userId, $action)
    {
        DB::table('log')->insert([
            'user_id' => $userId,
            'action' => $action,
            'created_at' => now(),
        ]);
    }

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

            $this->logAction($user->id, "Foydalanuvchi ID: {$user->id} xodim qo‘shdi (ID: {$employeeId})");

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Xodim muvaffaqiyatli yaratildi', 'employee' => $employeeId], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Xodim yaratishda xatolik: ' . $e->getMessage()], 500);
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
            'image' => 'nullable|image|max:20480',
            'status' => 'sometimes|boolean',
        ]);

        try {
            DB::beginTransaction();

            $employeeData = $request->only(['full_name', 'phone', 'address', 'role_id', 'hiring_date', 'type', 'department_id', 'group_id', 'payment_type', 'salary', 'status']);

            if ($request->hasFile('image')) {
                $employeeData['image_path'] = $request->file('image')->store('images', 'public');
            }

            DB::table('employee')->where('id', $request->employee_id)->update($employeeData);

            $this->logAction($user->id, "Foydalanuvchi ID: {$user->id} xodimni yangiladi (ID: {$request->employee_id})");

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
            DB::table('employee')->where('id', $request->employee_id)->update(['status' => false]);

            $this->logAction($user->id, "Foydalanuvchi ID: {$user->id} xodimni o‘chirdi (ID: {$request->employee_id})");

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Xodim muvaffaqiyatli o‘chirildi'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Xodimni o‘chirishda xatolik: ' . $e->getMessage()], 500);
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
