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
        $data = $request->validate([
            'name' => 'sometimes|string',
            'location' => 'sometimes|string',
            'users' => 'sometimes|array',
            'users.*' => 'integer|exists:users,id',
        ]);

        $warehouse = Warehouse::find($warehouseId);

        if (!$warehouse) {
            return response()->json(['message' => 'Warehouse not found'], 404);
        }

        if ($warehouse->branch_id !== auth()->user()->employee->branch_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (isset($data['name'])) {
            $warehouse->name = $data['name'];
        }
        if (isset($data['location'])) {
            $warehouse->location = $data['location'];
        }
        $warehouse->save();

        if (isset($data['users'])) {
            WarehouseRelatedUser::where('warehouse_id', $warehouse->id)->delete();

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
            ->with('stoks','stoks.item','users','stoks.item.unit','stoks.item.color','stoks.item.type')
            ->get();

        return response()->json($warehouses, 200);
    }

    public function getWarehouseUsers(): \Illuminate\Http\JsonResponse
    {
        $employee = auth()->user()->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $branchId = $employee->branch_id;

        $warehouses = User::where('type', 'aup')
            ->whereHas('employee', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            })
            ->get();

        return response()->json($warehouses, 200);
    }

}
