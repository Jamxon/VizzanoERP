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
            $query->whereIn('user_id', $employees)
                ->whereDate('created_at', $date);
        })
            ->with([
                'submodel',
                'orderModel.order',
                'orderModel.model',
                'qualityChecks' => function ($query) use ($date) {
                    $query->whereDate('created_at', $date)
                        ->with('qualityCheckDescriptions'); // Muhim: description'larni olish!
                }
            ])
            ->get()
            ->map(function ($orderSubModel) {
                $counts = $orderSubModel->qualityChecks->pluck('count', 'status');

                // Status false (0) bo'lgan barcha `qualityCheckDescriptions`ni yig‘ish
                $descriptionCounts = $orderSubModel->qualityChecks
                    ->where('status', false) // Faqat statusi false bo'lganlar
                    ->flatMap(fn($check) => $check->qualityCheckDescriptions) // Har bir checkning descriptionlarini olish
                    ->groupBy('id') // ID bo‘yicha guruhlash
                    ->map(fn($desc) => [
                        'id' => $desc->first()->id,
                        'name' => $desc->first()->name,
                        'description' => $desc->first()->description, // description maydoni
                        'count' => $desc->count() // Har bir descriptionning soni
                    ])
                    ->values(); // Indekslarni qayta tartiblash

                return [
                    'id' => $orderSubModel->id,
                    'submodel' => $orderSubModel->submodel,
                    'order' => $orderSubModel->orderModel->order ?? null,
                    'model' => $orderSubModel->orderModel->model ?? null,
                    'qualityChecksTrue' => $counts[1] ?? 0,
                    'qualityChecksFalse' => $counts[0] ?? 0,
                    'descriptions' => $descriptionCounts, // Hammasini olamiz
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
                'orderModel.submodels.submodel',
                'orderModel.sizes.size',
                'orderModel.submodels.group.group'
            )
            ->get();

        return response()->json($orders);
    }
}
