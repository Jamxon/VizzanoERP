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
        $data = json_decode($request->getContent(), true);

        $data = $data->validate([
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

    public function getWarehouseUsers()
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
