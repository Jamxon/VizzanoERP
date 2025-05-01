<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Log;
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

        $department = Department::where('id', auth()->user()->employee->department_id)->first();

        if (!$department) {
            return response()->json(['error' => 'Department not found'], 404);
        }

        $employees = $department->groups
            ->flatMap(fn($group) => $group->employees->map(fn($employee) => $employee->user->id));

        $orderSubModels = OrderSubModel::whereHas('qualityChecks', function ($query) use ($date, $employees) {
            $query->whereIn('user_id', $employees)
                ->whereDate('created_at', $date);
        })
            ->with([
                'submodel',
                'orderModel.order',
                'orderModel.model',
                'qualityChecks' => function ($query) use ($date) {
                    $query->whereDate('created_at', $date)
                        ->with('qualityCheckDescriptions');
                }
            ])
            ->get()
            ->map(function ($orderSubModel) {
                $counts = $orderSubModel->qualityChecks->groupBy('status')->map->count();

                $descriptionCounts = $orderSubModel->qualityChecks
                    ->where('status', false)
                    ->flatMap(fn($check) => $check->qualityCheckDescriptions)
                    ->groupBy('quality_description_id')
                    ->map(fn($desc) => [
                        'id' => $desc->first()->id,
                        'tarification' => $desc->first()->qualityDescription->tarification,
                        'count' => $desc->count(),
                    ])
                    ->values();

                return [
                    'id' => $orderSubModel->id,
                    'submodel' => $orderSubModel->submodel,
                    'order' => $orderSubModel->orderModel->order ?? null,
                    'model' => $orderSubModel->orderModel->model ?? null,
                    'qualityChecksTrue' => $counts[1] ?? 0,
                    'qualityChecksFalse' => $counts[0] ?? 0,
                    'qualityChecks' => $orderSubModel->qualityChecks,
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

        $oldConnection = OtkOrderGroup::where('order_sub_model_id', $request->order_sub_model_id)->first();

        if ($oldConnection) {
            $oldGroupName = $oldConnection->group->name ?? 'Unknown group';
            $oldData = [
                'order_sub_model' => $oldConnection->orderSubModel->submodel->name ?? 'Unknown model',
                'group' => $oldGroupName,
            ];
            $oldConnection->delete();
        } else {
            $oldData = null;
        }

        $newConnection = OtkOrderGroup::create([
            'order_sub_model_id' => $request->order_sub_model_id,
            'group_id' => $request->group_id,
        ]);

        $newGroupName = $newConnection->group->name ?? 'Unknown group';
        $newModelName = $newConnection->orderSubModel->submodel->name ?? 'Unknown model';

        Log::add(
            auth()->id(),
            'Buyurtma submodeli guruhga biriktirildi',
            'fastening',
            $oldData,
            [
                'order_sub_model' => $newModelName,
                'group' => $newGroupName,
            ]
        );

        return response()->json($newConnection);
    }

    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('status', $request->status)
            ->where('branch_id', auth()->user()->employee->branch_id)
            ->with([
                'orderModel.model',
                'orderModel.submodels.submodel',
                'orderModel.sizes.size',
                'orderModel.submodels.otkOrderGroup.group',
            ])
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id ?? null,
                    'name' => $order->name ?? null,
                    'status' => $order->status ?? null,
                    'order_model' => optional($order->orderModel)->id ? [
                        'id' => optional($order->orderModel)->id,
                        'model' => $order->orderModel->model ? [
                            'id' => $order->orderModel->model->id,
                            'name' => $order->orderModel->model->name,
                        ] : null,
                        'submodels' => optional($order->orderModel->submodels)->map(function ($submodel) {
                                return [
                                    'id' => optional($submodel)->id ?? null,
                                    'submodel' => $submodel->submodel ? [
                                        'id' => $submodel->submodel->id,
                                        'name' => $submodel->submodel->name,
                                    ] : null,
                                    'group' => $submodel->otkOrderGroup ? [
                                        'id' => $submodel->otkOrderGroup->group->id,
                                        'name' => $submodel->otkOrderGroup->group->name,
                                    ] : null,
                                ];
                            }) ?? [],
                        'sizes' => optional($order->orderModel->sizes)->map(function ($size) {
                                return [
                                    'id' => optional($size)->id ?? null,
                                    'size' => $size->size ? [
                                        'id' => $size->size->id,
                                        'name' => $size->size->name,
                                    ] : null,
                                ];
                            }) ?? [],
                    ] : null,
                ];
            });

        return response()->json($orders);
    }

    public function getGroups(): \Illuminate\Http\JsonResponse
    {
        $department = Department::where('id', auth()->user()->employee->department_id)->first();

        $groups = optional($department)->groups ?? [];

        return response()->json($groups);
    }

    public function changeOrderStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'status' => 'required|string|in:checking,checked',
        ]);

        $order = Order::find($request->order_id);

        if (!$order) {
            return response()->json(['error' => 'Buyurtma topilmadi'], 404);
        }

        $oldStatus = $order->status;

        $order->status = $request->status;
        $order->save();

        Log::add(
            auth()->id(),
            'Buyurtma holati o‘zgartirildi',
            'change',
            [
                'orderId' => $order->id ?? 'Noma’lum buyurtma',
                'old_status' => $oldStatus,
            ],
            [
                'orderId' => $order->id ?? 'Noma’lum buyurtma',
                'new_status' => $order->status,
            ]
        );

        return response()->json($order);
    }
}
