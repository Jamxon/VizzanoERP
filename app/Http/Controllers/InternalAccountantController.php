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

        // 1. Tarifikatsiyalarni yig‘ish (faqat employee biriktirilganlari)
        $tarifications = collect();

        foreach ($submodel->tarificationCategories as $category) {
            foreach ($category->tarifications as $tarification) {
                if ($tarification->employee) {
                    $tarifications->push([
                        'id' => $tarification->id,
                        'name' => $tarification->name,
                        'seconds' => $tarification->second,
                        'minutes' => round($tarification->second / 60, 4),
                        'assigned_employee_id' => $tarification->employee->id,
                        'assigned_employee_name' => $tarification->employee->name,
                    ]);
                }
            }
        }

        if ($tarifications->isEmpty()) {
            return response()->json(['message' => 'Tarifikatsiyalar yoki ularning xodimlari topilmadi'], 400);
        }

        // 2. Xodimlar bo‘yicha guruhlash
        $grouped = $tarifications->groupBy('assigned_employee_id');

        $employeePlans = [];

        foreach ($grouped as $employeeId => $tasks) {
            $employeeName = $tasks->first()['assigned_employee_name'];
            $remainingMinutes = 500;
            $usedMinutes = 0;

            // 1-qadam: tayyorlanadigan array
            $assigned = [];

            // Tartiblash: eng kam vaqt talab qiladigan ishlar birinchi
            $sortedTasks = $tasks->sortBy('minutes')->values();

            // 1-bosqich: har bir ishga kamida 1 dona berish
            foreach ($sortedTasks as $task) {
                if ($task['minutes'] > 0 && $remainingMinutes >= $task['minutes']) {
                    $assigned[] = [
                        'tarification_id' => $task['id'],
                        'tarification_name' => $task['name'],
                        'count' => 1,
                        'total_minutes' => round($task['minutes'], 2),
                        'minutes_per_unit' => $task['minutes'],
                    ];
                    $usedMinutes += $task['minutes'];
                    $remainingMinutes -= $task['minutes'];
                }
            }

            // 2-bosqich: qolgan vaqtni teng ravishda to‘ldirish
            $i = 0;
            while ($remainingMinutes > 0 && count($assigned) > 0) {
                $index = $i % count($assigned);
                $unit = $assigned[$index];
                $minutes = $unit['minutes_per_unit'];

                if ($remainingMinutes >= $minutes) {
                    $assigned[$index]['count'] += 1;
                    $assigned[$index]['total_minutes'] = round($assigned[$index]['count'] * $minutes, 2);
                    $usedMinutes += $minutes;
                    $remainingMinutes -= $minutes;
                } else {
                    break;
                }

                $i++;
            }

            // Yakuniy arraydan texnik maydonlarni olib tashlash
            foreach ($assigned as &$item) {
                unset($item['minutes_per_unit']);
            }

            $employeePlans[] = [
                'employee_id' => $employeeId,
                'employee_name' => $employeeName,
                'used_minutes' => round($usedMinutes, 2),
                'tarifications' => $assigned,
            ];
        }

        return response()->json([
            'message' => 'Kunlik plan yaratildi',
            'data' => $employeePlans,
        ]);
    }

}