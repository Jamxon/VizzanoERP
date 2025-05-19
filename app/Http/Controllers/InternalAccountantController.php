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
            'group_id' => 'required|exists:groups,id',
            'submodel_id' => 'required|exists:order_sub_models,id',
        ]);

        $group = Group::with('employees')->findOrFail($request->group_id);
        $employees = $group->employees;

        if ($employees->isEmpty()) {
            return response()->json(['message' => 'Guruhda hodimlar mavjud emas'], 400);
        }

        $submodel = OrderSubmodel::with(['tarificationCategories.tarifications' => function ($q) {
            $q->where('second', '>', 0);
        }])->findOrFail($request->submodel_id);

        // ✅ Efficiently collect tarifications
        $tarifications = $submodel->tarificationCategories
            ->pluck('tarifications')
            ->flatten()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'seconds' => floatval($t->second),
                    'minutes' => floatval($t->second) / 60,
                ];
            })->filter(function ($t) {
                return $t['minutes'] > 0;
            })->values();

        if ($tarifications->isEmpty()) {
            return response()->json(['message' => 'Tarificationlar topilmadi'], 400);
        }

        $plans = [];
        $employeeCount = $employees->count();
        $employeeStates = $employees->mapWithKeys(function ($e) {
            return [$e->id => ['used_minutes' => 0, 'name' => $e->name ?? 'No name']];
        })->toArray();

        $currentEmployeeIndex = 0;

        foreach ($tarifications as $tarification) {
            $tarificationLeft = 999999; // bu yerda kerakli sonni hisoblab kelishingiz mumkin

            while ($tarificationLeft > 0) {
                $employee = $employees[$currentEmployeeIndex];
                $employeeId = $employee->id;

                $used = $employeeStates[$employeeId]['used_minutes'];
                $available = 500 - $used;

                if ($available <= 0) {
                    $currentEmployeeIndex = ($currentEmployeeIndex + 1) % $employeeCount;
                    continue;
                }

                $maxCount = floor($available / $tarification['minutes']);
                if ($maxCount <= 0) {
                    $currentEmployeeIndex = ($currentEmployeeIndex + 1) % $employeeCount;
                    continue;
                }

                $assignCount = min($tarificationLeft, $maxCount);
                $assignMinutes = $assignCount * $tarification['minutes'];

                $plans[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employeeStates[$employeeId]['name'],
                    'tarification_id' => $tarification['id'],
                    'tarification_name' => $tarification['name'],
                    'count' => $assignCount,
                    'total_minutes' => round($assignMinutes, 2),
                ];

                $employeeStates[$employeeId]['used_minutes'] += $assignMinutes;
                $tarificationLeft -= $assignCount;

                $currentEmployeeIndex = ($currentEmployeeIndex + 1) % $employeeCount;

                // ✅ break if all employees are full
                if (collect($employeeStates)->every(fn ($e) => $e['used_minutes'] >= 500)) {
                    break 2;
                }
            }
        }

        return response()->json([
            'message' => 'Kunlik plan yaratildi',
            'data' => $plans,
        ]);
    }

}