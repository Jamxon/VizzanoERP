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

        // Barcha tarifikatsiyalarni to‘playmiz
        $tarifications = collect();

        foreach ($submodel->tarificationCategories as $category) {
            foreach ($category->tarifications as $tarification) {
                if ($tarification->employee) {
                    $tarifications->push([
                        'id' => $tarification->id,
                        'name' => $tarification->name,
                        'seconds' => $tarification->second,
                        'minutes_per_unit' => round($tarification->second / 60, 4),
                        'assigned_employee_id' => $tarification->employee->id,
                        'assigned_employee_name' => $tarification->employee->name,
                    ]);
                }
            }
        }

        if ($tarifications->isEmpty()) {
            return response()->json(['message' => 'Tarifikatsiyalar yoki ularning xodimlari topilmadi'], 400);
        }

        // Har bir xodim uchun tarifikatsiyalarni alohida ajratamiz
        $grouped = $tarifications->groupBy('assigned_employee_id');

        $finalPlan = [];

        foreach ($grouped as $employeeId => $employeeTarifs) {
            $remainingMinutes = 500;
            $usedMinutes = 0;
            $resultTarifs = [];

            // 1-bosqich: har bir tarifga 1 dona beramiz
            foreach ($employeeTarifs as $tarif) {
                if ($remainingMinutes >= $tarif['minutes_per_unit']) {
                    $resultTarifs[] = [
                        'tarification_id' => $tarif['id'],
                        'tarification_name' => $tarif['name'],
                        'count' => 1,
                        'total_minutes' => round($tarif['minutes_per_unit'], 2),
                        'minutes_per_unit' => $tarif['minutes_per_unit'],
                    ];
                    $usedMinutes += $tarif['minutes_per_unit'];
                    $remainingMinutes -= $tarif['minutes_per_unit'];
                }
            }

            // 2-bosqich: qolgan vaqtni navbat bilan to‘ldirish
            $i = 0;
            while ($remainingMinutes > 0 && count($resultTarifs) > 0) {
                $index = $i % count($resultTarifs);
                $tarif = &$resultTarifs[$index];
                $unitTime = $tarif['minutes_per_unit'];

                if ($remainingMinutes >= $unitTime) {
                    $tarif['count'] += 1;
                    $tarif['total_minutes'] = round($tarif['count'] * $unitTime, 2);
                    $usedMinutes += $unitTime;
                    $remainingMinutes -= $unitTime;
                } else {
                    break;
                }
                $i++;
            }

            // So'nggi tozalash
            foreach ($resultTarifs as &$t) {
                unset($t['minutes_per_unit']);
            }

            if (count($resultTarifs)) {
                $finalPlan[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employeeTarifs->first()['assigned_employee_name'],
                    'used_minutes' => round($usedMinutes, 2),
                    'tarifications' => $resultTarifs,
                ];
            }
        }

        return response()->json([
            'message' => 'Kunlik plan yaratildi',
            'data' => $finalPlan,
        ]);
    }

}