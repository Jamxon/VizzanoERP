<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseRelatedUser;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function warehouseStore(Request $request): \Illuminate\Http\JsonResponse
    {

        $data = $request->validate([
            'name' => 'required|string',
            'location' => 'required|string',
            'users' => 'required|array',
            'users.*' => 'required|integer|exists:users,id',
        ]);

        $warehouse = Warehouse::create([
            'name' => $data['name'],
            'location' => $data['location'],
            'branch_id' => auth()->user()->employee->branch_id,
        ]);

        foreach ($data['users'] as $user) {
            WarehouseRelatedUser::create([
                'warehouse_id' => $warehouse->id,
                'user_id' => $user,
            ]);
        }

        return response()->json([
            'message' => 'Warehouse created successfully',
            'warehouse' => $warehouse,
        ], 201);
    }

    public function warehouseUpdate(Request $request, $warehouseId): \Illuminate\Http\JsonResponse
    {
        // Ma'lumotlarni validatsiya qilish
        $data = $request->validate([
            'name' => 'sometimes|string', // "sometimes" orqali maydon ixtiyoriy bo'lishi mumkin
            'location' => 'sometimes|string',
            'users' => 'sometimes|array', // Foydalanuvchilar ro'yxati ixtiyoriy
            'users.*' => 'integer|exists:users,id', // Har bir foydalanuvchi ID-si tekshiriladi
        ]);

        // Omborni topish
        $warehouse = Warehouse::find($warehouseId);

        if (!$warehouse) {
            return response()->json(['message' => 'Warehouse not found'], 404);
        }

        // Omborga tegishli branch_id ni tekshirish
        if ($warehouse->branch_id !== auth()->user()->employee->branch_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Ombor ma'lumotlarini yangilash (agar kiritilgan bo'lsa)
        if (isset($data['name'])) {
            $warehouse->name = $data['name'];
        }
        if (isset($data['location'])) {
            $warehouse->location = $data['location'];
        }
        $warehouse->save();

        // Foydalanuvchilarni yangilash (agar kiritilgan bo'lsa)
        if (isset($data['users'])) {
            // Avvalgi foydalanuvchilarni o'chirish
            WarehouseRelatedUser::where('warehouse_id', $warehouse->id)->delete();

            // Yangi foydalanuvchilarni qo'shish
            foreach ($data['users'] as $userId) {
                WarehouseRelatedUser::create([
                    'warehouse_id' => $warehouse->id,
                    'user_id' => $userId,
                ]);
            }
        }

        return response()->json([
            'message' => 'Warehouse updated successfully',
            'warehouse' => $warehouse,
        ], 200);
    }

    public function getWarehouse(): \Illuminate\Http\JsonResponse
    {
        $employee = auth()->user()->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $warehouses = Warehouse::where('branch_id', $employee->branch_id)
            ->with('stoks','stoks.item','users')
            ->get();

        return response()->json($warehouses, 200);
    }

    public function getWarehouseUsers(): \Illuminate\Http\JsonResponse
    {
        $employee = auth()->user()->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        // Employee modelidan branch_id ni olish
        $branchId = $employee->branch_id;

        // branch_id orqali foydalanuvchilarni filtrlash
        $warehouses = User::where('role_id', 3)
            ->whereHas('employee', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->get();

        return response()->json($warehouses, 200);
    }

}
