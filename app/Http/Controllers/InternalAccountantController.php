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

        // Submodel bilan bog‘liq tarifikatsiya kategoriyalarini va tarifikatsiyalarni ularning employee’lari bilan olish
        $submodel = OrderSubmodel::with([
            'tarificationCategories' => function ($q) {
                $q->select('id', 'submodel_id');
            },
            'tarificationCategories.tarifications' => function ($q) {
                $q->select('id', 'name', 'second', 'tarification_category_id', 'employee_id')
                    ->where('second', '>', 0);
            },
            'tarificationCategories.tarifications.employee' => function ($q) {
                $q->select('id', 'name');
            }
        ])->findOrFail($request->submodel_id);

        // Tarifikatsiyalarni yig‘ish
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

        // Xodimlar holatini yaratish
        $employeeStates = [];
        $allEmployeesInTarifications = collect();

        foreach ($tarifications as $tarification) {
            $employee = $tarification['assigned_employee'];
            if (!$allEmployeesInTarifications->contains('id', $employee->id)) {
                $allEmployeesInTarifications->push($employee);
            }
        }

        foreach ($allEmployeesInTarifications as $employee) {
            $employeeStates[$employee->id] = [
                'id' => $employee->id,
                'name' => $employee->name ?? 'No name',
                'used_minutes' => 0,
                'plans' => []
            ];
        }

        // Tarifikatsiyalarni kamayish tartibida saralash
        $tarifications = $tarifications->sortByDesc('minutes')->values();

        foreach ($tarifications as $tarification) {
            $employeeId = $tarification['assigned_employee']->id;
            $assignedEmployeeIds = [$employeeId];

            $totalMinutesAvailable = count($assignedEmployeeIds) * 500;
            $totalWorkNeeded = ceil($totalMinutesAvailable * 0.8 / $tarifications->count());
            $tarificationLeft = $totalWorkNeeded;

            $baseAllocation = floor($tarificationLeft / count($assignedEmployeeIds));

            if ($baseAllocation > 0) {
                foreach ($assignedEmployeeIds as $employeeId) {
                    $state = &$employeeStates[$employeeId];
                    $available = 500 - $state['used_minutes'];
                    $maxCount = floor($available / $tarification['minutes']);
                    $assignCount = min($baseAllocation, $maxCount);

                    if ($assignCount > 0) {
                        $assignMinutes = $assignCount * $tarification['minutes'];

                        if (!isset($state['plans'][$tarification['id']])) {
                            $state['plans'][$tarification['id']] = [
                                'employee_id' => $employeeId,
                                'employee_name' => $state['name'],
                                'tarification_id' => $tarification['id'],
                                'tarification_name' => $tarification['name'],
                                'count' => 0,
                                'total_minutes' => 0,
                            ];
                        }

                        $state['plans'][$tarification['id']]['count'] += $assignCount;
                        $state['plans'][$tarification['id']]['total_minutes'] += round($assignMinutes, 2);

                        $state['used_minutes'] += $assignMinutes;
                        $tarificationLeft -= $assignCount;
                    }
                }
            }

            // Qolgan ishni eng kam yuklangan xodimga berish
            while ($tarificationLeft > 0) {
                $minUsed = PHP_INT_MAX;
                $selectedEmployeeId = null;

                foreach ($assignedEmployeeIds as $employeeId) {
                    $state = $employeeStates[$employeeId];
                    if ($state['used_minutes'] < 500 && $state['used_minutes'] < $minUsed) {
                        $minUsed = $state['used_minutes'];
                        $selectedEmployeeId = $employeeId;
                    }
                }

                if ($selectedEmployeeId === null) break;

                $available = 500 - $employeeStates[$selectedEmployeeId]['used_minutes'];
                $maxCount = floor($available / $tarification['minutes']);
                $maxAssignAtOnce = ceil($tarificationLeft / max(1, count($assignedEmployeeIds) / 2));
                $assignCount = min($maxAssignAtOnce, $maxCount, $tarificationLeft);

                if ($assignCount <= 0) break;

                $assignMinutes = $assignCount * $tarification['minutes'];

                if (!isset($employeeStates[$selectedEmployeeId]['plans'][$tarification['id']])) {
                    $employeeStates[$selectedEmployeeId]['plans'][$tarification['id']] = [
                        'employee_id' => $selectedEmployeeId,
                        'employee_name' => $employeeStates[$selectedEmployeeId]['name'],
                        'tarification_id' => $tarification['id'],
                        'tarification_name' => $tarification['name'],
                        'count' => 0,
                        'total_minutes' => 0,
                    ];
                }

                $employeeStates[$selectedEmployeeId]['plans'][$tarification['id']]['count'] += $assignCount;
                $employeeStates[$selectedEmployeeId]['plans'][$tarification['id']]['total_minutes'] += round($assignMinutes, 2);
                $employeeStates[$selectedEmployeeId]['used_minutes'] += $assignMinutes;
                $tarificationLeft -= $assignCount;

                $allFull = true;
                foreach ($assignedEmployeeIds as $employeeId) {
                    if ($employeeStates[$employeeId]['used_minutes'] < 500) {
                        $allFull = false;
                        break;
                    }
                }

                if ($allFull) break;
            }
        }

        // Javob formatlash
        $flattenedPlans = [];
        foreach ($employeeStates as $state) {
            foreach ($state['plans'] as $plan) {
                if ($plan['count'] > 0) {
                    $flattenedPlans[] = $plan;
                }
            }
        }

        return response()->json([
            'message' => 'Kunlik plan yaratildi',
            'data' => $flattenedPlans,
        ]);
    }

}