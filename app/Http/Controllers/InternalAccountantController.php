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

        // Load only necessary data with eager loading
        $group = Group::select('id')->with(['employees' => function($q) {
            $q->select('id', 'name', 'group_id');
        }])->findOrFail($request->group_id);

        $employees = $group->employees;

        if ($employees->isEmpty()) {
            return response()->json(['message' => 'Guruhda hodimlar mavjud emas'], 400);
        }

        // Optimize submodel loading by selecting only needed columns
        $submodel = OrderSubmodel::select('id')
            ->with(['tarificationCategories:id,submodel_id',
                'tarificationCategories.tarifications' => function ($q) {
                    $q->select('id', 'name', 'second', 'tarification_category_id')
                        ->where('second', '>', 0);
                }])
            ->findOrFail($request->submodel_id);

        // Collect tarifications efficiently
        $tarifications = collect();
        foreach ($submodel->tarificationCategories as $category) {
            foreach ($category->tarifications as $tarification) {
                $minutes = floatval($tarification->second) / 60;
                if ($minutes > 0) {
                    $tarifications->push([
                        'id' => $tarification->id,
                        'name' => $tarification->name,
                        'seconds' => floatval($tarification->second),
                        'minutes' => $minutes,
                    ]);
                }
            }
        }

        if ($tarifications->isEmpty()) {
            return response()->json(['message' => 'Tarificationlar topilmadi'], 400);
        }

        // Calculate total work needed
        $totalMinutesAvailable = $employees->count() * 500;
        $totalTarificationCount = $tarifications->count();

        // Prepare employee state tracking
        $employeeStates = [];
        foreach ($employees as $employee) {
            $employeeStates[$employee->id] = [
                'id' => $employee->id,
                'name' => $employee->name ?? 'No name',
                'used_minutes' => 0,
                'plans' => []
            ];
        }

        // Allocate work efficiently
        $plans = [];
        $employeeCount = $employees->count();

        // Sort tarifications by time (descending) to optimize allocation
        $tarifications = $tarifications->sortByDesc('minutes')->values();

        // Track tarification distribution for better balancing
        $tarificationDistribution = [];
        foreach ($employees as $employee) {
            $tarificationDistribution[$employee->id] = [];
            foreach ($tarifications as $tarification) {
                $tarificationDistribution[$employee->id][$tarification['id']] = 0;
            }
        }

        // Calculate total workload per tarification
        $totalWorkPerTarification = [];
        foreach ($tarifications as $tarification) {
            // Each tarification gets roughly equal share of total capacity
            $totalWorkPerTarification[$tarification['id']] = ceil(500 * $employeeCount * 0.8 / $tarifications->count());
        }

        // First pass: distribute each tarification among all employees
        foreach ($tarifications as $tarification) {
            $tarificationLeft = $totalWorkPerTarification[$tarification['id']];

            // Initial distribution - give each employee some work of each type
            $baseAllocation = floor($tarificationLeft / $employeeCount);
            if ($baseAllocation > 0) {
                foreach ($employeeStates as $employeeId => &$state) {
                    $available = 500 - $state['used_minutes'];
                    $maxCount = floor($available / $tarification['minutes']);
                    $assignCount = min($baseAllocation, $maxCount);

                    if ($assignCount > 0) {
                        $assignMinutes = $assignCount * $tarification['minutes'];

                        // Add to employee's plan
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
                        $tarificationDistribution[$employeeId][$tarification['id']] += $assignCount;
                    }
                }
            }

            // Second pass: distribute remaining work
            while ($tarificationLeft > 0) {
                // Find the employee with the lowest count of this tarification who still has available minutes
                $minCount = PHP_INT_MAX;
                $selectedEmployeeId = null;

                foreach ($employeeStates as $employeeId => $state) {
                    if ($state['used_minutes'] < 500 &&
                        ($tarificationDistribution[$employeeId][$tarification['id']] < $minCount)) {
                        $minCount = $tarificationDistribution[$employeeId][$tarification['id']];
                        $selectedEmployeeId = $employeeId;
                    }
                }

                // If no employee has capacity, break
                if ($selectedEmployeeId === null) {
                    break;
                }

                $available = 500 - $employeeStates[$selectedEmployeeId]['used_minutes'];
                $maxCount = floor($available / $tarification['minutes']);

                // Limit how much we assign at once for better distribution
                $maxAssignAtOnce = ceil($tarificationLeft / max(1, $employeeCount / 2));
                $assignCount = min($maxAssignAtOnce, $maxCount, $tarificationLeft);

                if ($assignCount <= 0) {
                    break; // No more work can be assigned
                }

                $assignMinutes = $assignCount * $tarification['minutes'];

                // Add to employee's plan
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
                $tarificationDistribution[$selectedEmployeeId][$tarification['id']] += $assignCount;

                // Break if all employees are full
                if (collect($employeeStates)->every(fn ($e) => $e['used_minutes'] >= 500)) {
                    break;
                }
            }
        }

        // Flatten plans for response
        $flattenedPlans = [];
        foreach ($employeeStates as $employeeId => $state) {
            foreach ($state['plans'] as $tarificationId => $plan) {
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