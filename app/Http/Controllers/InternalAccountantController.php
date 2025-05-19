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
            ->with(['tarificationCategories:id,order_sub_model_id',
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
        $totalTarificationMinutes = $tarifications->sum(function($t) {
            return $t['minutes'] * 1000; // Arbitrary large number to ensure all work is covered
        });

        // Calculate how many employees we need to handle this work
        $requiredEmployeeCapacity = ceil($totalTarificationMinutes / 500);

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
        $currentEmployeeIndex = 0;
        $employeeCount = $employees->count();

        // Sort tarifications by time (descending) to optimize allocation
        $tarifications = $tarifications->sortByDesc('minutes')->values();

        foreach ($tarifications as $tarification) {
            // Set a reasonable limit based on total required capacity
            $tarificationLeft = ceil(500 * $requiredEmployeeCapacity / $tarifications->count());

            while ($tarificationLeft > 0) {
                // Find employee with minimum used minutes
                $minUsedMinutes = 500;
                $minEmployeeId = null;

                foreach ($employeeStates as $id => $state) {
                    if ($state['used_minutes'] < $minUsedMinutes) {
                        $minUsedMinutes = $state['used_minutes'];
                        $minEmployeeId = $id;
                    }
                }

                // If all employees are full, break
                if ($minUsedMinutes >= 500) {
                    break;
                }

                $employeeId = $minEmployeeId;
                $available = 500 - $employeeStates[$employeeId]['used_minutes'];

                if ($available <= 0) {
                    break; // All employees are full
                }

                $maxCount = floor($available / $tarification['minutes']);
                if ($maxCount <= 0) {
                    break; // No more work can be assigned
                }

                $assignCount = min($tarificationLeft, $maxCount);
                $assignMinutes = $assignCount * $tarification['minutes'];

                // Add to employee's plan
                if (!isset($employeeStates[$employeeId]['plans'][$tarification['id']])) {
                    $employeeStates[$employeeId]['plans'][$tarification['id']] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $employeeStates[$employeeId]['name'],
                        'tarification_id' => $tarification['id'],
                        'tarification_name' => $tarification['name'],
                        'count' => 0,
                        'total_minutes' => 0,
                    ];
                }

                $employeeStates[$employeeId]['plans'][$tarification['id']]['count'] += $assignCount;
                $employeeStates[$employeeId]['plans'][$tarification['id']]['total_minutes'] += round($assignMinutes, 2);

                $employeeStates[$employeeId]['used_minutes'] += $assignMinutes;
                $tarificationLeft -= $assignCount;

                // Break if tarification is exhausted or all employees are full
                if ($tarificationLeft <= 0 || collect($employeeStates)->every(fn ($e) => $e['used_minutes'] >= 500)) {
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