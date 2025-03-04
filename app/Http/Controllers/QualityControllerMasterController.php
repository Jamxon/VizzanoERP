<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Order;
use App\Models\OrderSubModel;
use App\Models\OtkOrderGroup;
use App\Models\QualityCheck;
use Illuminate\Http\Request;

class QualityControllerMasterController extends Controller
{
    public function results(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = $request->input('date') ?? now();
        $department = Department::where('responsible_user_id', auth()->id())->first();
        if (!$department) {
            return response()->json(['error' => 'Department not found'], 404);
        }

        $employees = $department->groups
            ->flatMap(fn($group) => $group->employees->map(fn($employee) => $employee->user->id));

        $orderSubModels = OrderSubModel::whereHas('qualityChecks', function ($query) use ($date, $employees) {
            $query->whereIn('user_id', $employees);
            $query->whereDate('created_at', $date);
        })
            ->with([
                'submodel',
                'orderModel.order',
                'orderModel.model',
                'qualityChecks' => function ($query) {
                    $query->selectRaw('order_sub_model_id, status, COUNT(*) as count')
                        ->whereDate('created_at', now())
                        ->groupBy('order_sub_model_id', 'status');
                }
            ])
            ->get()
            ->map(function ($orderSubModel) {
                $counts = $orderSubModel->qualityChecks->pluck('count', 'status');
                return [
                    'id' => $orderSubModel->id,
                    'submodel' => $orderSubModel->submodel,
                    'order' => $orderSubModel->orderModel->order ?? null,
                    'model' => $orderSubModel->orderModel->model ?? null,
                    'qualityChecksTrue' => $counts[1] ?? 0,
                    'qualityChecksFalse' => $counts[0] ?? 0,
                ];
            });

        return response()->json($orderSubModels);
    }

    public function fasteningOrderToGroup(Request $request): \Illuminate\Http\JsonResponse
    {

        $request->validate([
            'order_sub_model_id' => 'required|integer|exists:order_sub_models,id',
            'group_id' => 'required|integer|exists:groups,id',
        ]);

        $otkOrderGroup = OtkOrderGroup::create([
            'order_sub_model_id' => $request->order_sub_model_id,
            'group_id' => $request->group_id,
        ]);

        return response()->json($otkOrderGroup);
    }

    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('status', $request->status)
            ->with(
                'orderModel.model',
                'orderModel.orderSubModels.submodel',
                'orderModel.orderSubModels.sizes.size',
                'orderModel.orderSubModels.group.group'
            )
            ->get();

        return response()->json($orders);
    }
}
