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
        // Sana parametrini olish yoki hozirgi sanani ishlatish
        $date = $request->input('date') ?? now();

        // Avtorizatsiyadan o'tgan foydalanuvchi uchun departmentni topish
        $department = Department::where('responsible_user_id', auth()->id())->first();

        // Agar department topilmasa, xatolik qaytarish
        if (!$department) {
            return response()->json(['error' => 'Department not found'], 404);
        }

        // Departmentdagi barcha guruhlarning xodimlarini olish
        $employees = $department->groups
            ->flatMap(fn($group) => $group->employees->map(fn($employee) => $employee->user->id));

        // OrderSubModel ma'lumotlarini olish
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
                        ->with('qualityCheckDescriptions'); // Description'larni yuklash
                }
            ])
            ->get()
            ->map(function ($orderSubModel) {
                // QualityCheck statuslari bo'yicha hisoblash
                $counts = $orderSubModel->qualityChecks->groupBy('status')->map->count();

                // QualityCheck status false (0) bo'lsa, description'lar bo'yicha guruhlash
                $descriptionCounts = $orderSubModel->qualityChecks
                    ->where('status', false) // Faqat statusi false bo'lganlar
                    ->flatMap(fn($check) => $check->qualityCheckDescriptions)
                    ->groupBy('quality_description_id')
                    ->map(fn($desc) => [
                        'id' => $desc->first()->id,
                        'description' => $desc->first()->qualityDescription->description,
                        'count' => $desc->count(), // Har bir descriptionning soni
                    ])
                    ->values(); // Indekslarni qayta tartiblash

                return [
                    'id' => $orderSubModel->id,
                    'submodel' => $orderSubModel->submodel,
                    'order' => $orderSubModel->orderModel->order ?? null,
                    'model' => $orderSubModel->orderModel->model ?? null,
                    'qualityChecksTrue' => $counts[1] ?? 0, // Status true (1) bo'lganlar soni
                    'qualityChecksFalse' => $counts[0] ?? 0, // Status false (0) bo'lganlar soni
                    'descriptions' => $descriptionCounts, // Tanlangan descriptionlar va soni
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

        $orderSubModel = OtkOrderGroup::where('order_sub_model_id', $request->order_sub_model_id)->first();

        if ($orderSubModel){
            $orderSubModel->delete();
        }

        $otkOrderGroup = OtkOrderGroup::create([
            'order_sub_model_id' => $request->order_sub_model_id,
            'group_id' => $request->group_id,
        ]);

        return response()->json($otkOrderGroup);
    }

    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('status', $request->status)
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
        $department = Department::where('responsible_user_id', auth()->id())->first();

        $groups = optional($department)->groups ?? [];

        return response()->json($groups);
    }
}
