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
                    $query->selectRaw('order_sub_model_id, status, COUNT(*) as count')
                        ->whereDate('created_at', $date) // Sana parametri bilan ishlash
                        ->groupBy('order_sub_model_id', 'status');
                },
                'qualityChecks.qualityCheckDescriptions' // Pivot orqali bog'langan description'lar
            ])
            ->get()
            ->map(function ($orderSubModel) {
                // QualityCheck statuslari bo'yicha hisoblash
                $counts = $orderSubModel->qualityChecks->pluck('count', 'status');

                // QualityCheck status false (0) bo'lsa, description'lar bo'yicha guruhlash
                $descriptionCounts = $orderSubModel->qualityChecks
                    ->where('status', false) // Faqat statusi false bo'lganlar
                    ->flatMap(function ($check) {
                        return $check->qualityCheckDescriptions->map(function ($description) {
                            return [
                                'id' => $description->id,
                                'name' => $description->name,
                                'description' => $description->description, // description maydoni
                            ];
                        });
                    })
                    ->groupBy('id') // ID bo'yicha guruhlash
                    ->map(function ($desc) {
                        return [
                            'id' => $desc->first()['id'],
                            'name' => $desc->first()['name'],
                            'description' => $desc->first()['description'], // description maydoni
                            'count' => $desc->count(), // Har bir descriptionning soni
                        ];
                    })
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
