<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Order;
use App\Models\OrderSubModel;
use App\Models\Tarification;
use App\Models\TarificationCategory;
use Illuminate\Http\Request;

class InternalAccountantController extends Controller
{
    public function getOrders(Request $request): \Illuminate\Http\JsonResponse
    {
        $orders = Order::where('branch_id', auth()->user()->employee->branch_id)
            ->whereIn('status', ['cutting','pending','tailoring', 'tailored', 'checking', 'checked', 'packaging', 'completed'])
            ->with(
                'orderModel',
                'orderModel.model',
                'orderModel.material',
                'orderModel.submodels.submodel',
            )
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($orders);
    }

    public function showOrder(Order $order): \Illuminate\Http\JsonResponse
    {
        $order->load(
            'orderModel',
            'orderModel.model',
            'orderModel.material',
            'orderModel.submodels.submodel',
            'orderModel.submodels.submodelSpend',
            'orderModel.submodels.tarificationCategories',
            'orderModel.submodels.tarificationCategories.tarifications',
            'orderModel.submodels.tarificationCategories.tarifications.employee',
            'orderModel.submodels.tarificationCategories.tarifications.razryad',
            'orderModel.submodels.tarificationCategories.tarifications.typewriter',
        );

        return response()->json($order);
    }

    public function searchTarifications(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = $request->input('search');
        $tarifications = Tarification::where('name', 'like', "%$search%")
                ->orWhere('razryad_id', 'like', "%$search%")
                ->orWhere('typewriter_id', 'like', "%$search%")
                ->orWhere('code', 'like', "%$search%")
            ->with(
                'tarifications',
                'tarifications.employee',
                'tarifications.razryad',
                'tarifications.typewriter',
            )
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($tarifications);
    }

    public function generateDailyPlan(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'submodel_id' => 'required|exists:order_sub_models,id',
        ]);

        $submodel = OrderSubmodel::with([
            'tarificationCategories' => fn($q) => $q->select('id', 'submodel_id'),
            'tarificationCategories.tarifications' => fn($q) => $q->select('id', 'name', 'second', 'tarification_category_id', 'user_id')->where('second', '>', 0),
            'tarificationCategories.tarifications.employee' => fn($q) => $q->select('id', 'name'),
        ])->findOrFail($request->submodel_id);

        $tarifications = collect();

        foreach ($submodel->tarificationCategories as $category) {
            foreach ($category->tarifications as $tarification) {
                if ($tarification->employee) {
                    $tarifications->push([
                        'id' => $tarification->id,
                        'name' => $tarification->name,
                        'seconds' => $tarification->second,
                        'minutes' => round($tarification->second / 60, 2),
                        'assigned_employee' => $tarification->employee,
                    ]);
                }
            }
        }

        if ($tarifications->isEmpty()) {
            return response()->json(['message' => 'Tarifikatsiyalar yoki ularning xodimlari topilmadi'], 400);
        }

        // Har bir ishchi uchun tarifikatsiyalarni ajratib olish
        $groupedTarifications = $tarifications->groupBy(fn($item) => $item['assigned_employee']->id);

        $employeePlans = [];

        foreach ($groupedTarifications as $employeeId => $tarifs) {
            $employee = $tarifs->first()['assigned_employee'];

            $remainingMinutes = 500;
            $usedMinutes = 0;
            $employeeTarifications = [];

            // Eng kam vaqt talab qiladigan ishlarni oldinga qo‘y
            $sortedTarifs = $tarifs->sortBy('minutes');

            foreach ($sortedTarifs as $tarif) {
                $minutesPerUnit = $tarif['minutes'];

                if ($minutesPerUnit <= 0 || $remainingMinutes < $minutesPerUnit) {
                    continue;
                }

                $maxCount = floor($remainingMinutes / $minutesPerUnit);
                $totalMinutes = round($maxCount * $minutesPerUnit, 2);

                if ($maxCount > 0) {
                    $employeeTarifications[] = [
                        'tarification_id' => $tarif['id'],
                        'tarification_name' => $tarif['name'],
                        'count' => $maxCount,
                        'total_minutes' => $totalMinutes,
                    ];

                    $usedMinutes += $totalMinutes;
                    $remainingMinutes -= $totalMinutes;
                }

                if ($remainingMinutes <= 0) {
                    break;
                }
            }

            if (count($employeeTarifications)) {
                $employeePlans[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee->name ?? 'No name',
                    'used_minutes' => round($usedMinutes, 2),
                    'tarifications' => $employeeTarifications,
                ];
            }
        }

        return response()->json([
            'message' => 'Kunlik plan yaratildi',
            'data' => $employeePlans,
        ]);
    }

}