<?php

namespace App\Http\Controllers;

use App\Models\Log;
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

            Log::add($user->id, 'Yangi xodim qo‘shildi', null, $employeeData);

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

            Log::add($user->id, 'Xodim yangilandi', $oldData, $newData);

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
            Log::add($user->id, 'Xodim o‘chirildi', (array) $employee, null);

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
            Log::add($user->id, 'Xodim qayta tiklandi', null, (array) $employee);

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Xodim qayta tiklandi'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Xodimni qayta tiklashda xatolik: ' . $e->getMessage()], 500);
        }
    }
}
