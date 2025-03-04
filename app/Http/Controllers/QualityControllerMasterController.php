<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\OrderSubModel;
use App\Models\QualityCheck;
use Illuminate\Http\Request;

class QualityControllerMasterController extends Controller
{
    public function results(): \Illuminate\Http\JsonResponse
    {
        $department = Department::where('responsible_user_id', auth()->id())->first();
        if (!$department) {
            return response()->json(['error' => 'Department not found'], 404);
        }

        $employees = $department->groups
            ->flatMap(fn($group) => $group->employees->map(fn($employee) => $employee->user->id));

        $orderSubModels = OrderSubModel::whereHas('qualityChecks', function ($query) use ($employees) {
            $query->whereIn('user_id', $employees);
            $query->whereDate('created_at', now());
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
}
