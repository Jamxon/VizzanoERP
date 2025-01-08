<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\WarehouseRelatedUser;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function warehouseStore(Request $request)
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

    public function getWarehouse()
    {
        $warehouses = Warehouse::where('branch_id', auth()->user()->employee->branch_id)->get();

        return response()->json($warehouses, 200);
    }
}
