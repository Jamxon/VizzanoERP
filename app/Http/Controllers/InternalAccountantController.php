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

        // Tarifikatsiyalarga bog'langan xodimlarni olish
        $submodel = OrderSubmodel::select('id')
            ->with([
                'tarificationCategories:id,submodel_id',
                'tarificationCategories.tarifications' => function ($q) {
                    $q->select('id', 'name', 'second', 'tarification_category_id')
                        ->where('second', '>', 0)
                        ->with(['employees' => function($query) use ($request) {
                            $query->select('employees.id', 'employees.name')
                                ->where('employees.group_id', $request->group_id);
                        }]);
                }
            ])
            ->findOrFail($request->submodel_id);

        // Tarifikatsiyalarni yig'ish va ularning xodimlarini belgilash
        $tarifications = collect();
        foreach ($submodel->tarificationCategories as $category) {
            foreach ($category->tarifications as $tarification) {
                $minutes = floatval($tarification->second) / 60;
                if ($minutes > 0) {
                    // Faqat shu tarifikatsiyaga bog'langan va ko'rsatilgan guruhga tegishli xodimlarni olish
                    $assignedEmployees = $tarification->employees
                        ->where('group_id', $request->group_id);

                    if ($assignedEmployees->isNotEmpty()) {
                        $tarifications->push([
                            'id' => $tarification->id,
                            'name' => $tarification->name,
                            'seconds' => floatval($tarification->second),
                            'minutes' => $minutes,
                            'assigned_employees' => $assignedEmployees
                        ]);
                    }
                }
            }
        }

        if ($tarifications->isEmpty()) {
            return response()->json(['message' => 'Tarifikatsiyalar yoki ularga bog\'langan xodimlar topilmadi'], 400);
        }

        // Xodimlar holatini kuzatish uchun tayyorlash
        $employeeStates = [];

        // Barcha xodimlarni (tarifikatsiyalarga bog'langan) ro'yxatini yaratish
        $allEmployeesInTarifications = collect();
        foreach ($tarifications as $tarification) {
            foreach ($tarification['assigned_employees'] as $employee) {
                if (!$allEmployeesInTarifications->contains('id', $employee->id)) {
                    $allEmployeesInTarifications->push($employee);
                }
            }
        }

        if ($allEmployeesInTarifications->isEmpty()) {
            return response()->json(['message' => 'Tarifikatsiyalarga bog\'langan xodimlar topilmadi'], 400);
        }

        // Xodimlar uchun boshlang'ich holatni o'rnatish
        foreach ($allEmployeesInTarifications as $employee) {
            $employeeStates[$employee->id] = [
                'id' => $employee->id,
                'name' => $employee->name ?? 'No name',
                'used_minutes' => 0,
                'plans' => []
            ];
        }

        // Tarifikatsiyalarni vaqtga ko'ra saralash (kamayish tartibida)
        $tarifications = $tarifications->sortByDesc('minutes')->values();

        // Har bir tarifikatsiya uchun ish taqsimlash
        foreach ($tarifications as $tarification) {
            // Faqat shu tarifikatsiyaga bog'langan xodimlar bilan ishlash
            $assignedEmployeeIds = $tarification['assigned_employees']->pluck('id')->toArray();

            if (empty($assignedEmployeeIds)) {
                continue; // Agar bu tarifikatsiyaga xodimlar bog'lanmagan bo'lsa, o'tkazib yuborish
            }

            // Har bir xodim uchun mavjud bo'lgan umumiy vaqt
            $totalMinutesAvailable = count($assignedEmployeeIds) * 500;

            // Har bir tarifikatsiya uchun taxminiy ish hajmi
            $totalWorkNeeded = ceil($totalMinutesAvailable * 0.8 / $tarifications->count());
            $tarificationLeft = $totalWorkNeeded;

            // Har bir xodimga asosiy taqsimlash
            $baseAllocation = floor($tarificationLeft / count($assignedEmployeeIds));

            if ($baseAllocation > 0) {
                foreach ($assignedEmployeeIds as $employeeId) {
                    $state = &$employeeStates[$employeeId];
                    $available = 500 - $state['used_minutes'];
                    $maxCount = floor($available / $tarification['minutes']);
                    $assignCount = min($baseAllocation, $maxCount);

                    if ($assignCount > 0) {
                        $assignMinutes = $assignCount * $tarification['minutes'];

                        // Xodimning rejasiga qo'shish
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

            // Qolgan ishni tarqatish
            while ($tarificationLeft > 0) {
                // Eng kam yuklangan xodimni topish
                $minUsed = PHP_INT_MAX;
                $selectedEmployeeId = null;

                foreach ($assignedEmployeeIds as $employeeId) {
                    $state = $employeeStates[$employeeId];
                    if ($state['used_minutes'] < 500 && $state['used_minutes'] < $minUsed) {
                        $minUsed = $state['used_minutes'];
                        $selectedEmployeeId = $employeeId;
                    }
                }

                // Agar bo'sh xodim bo'lmasa, to'xtatish
                if ($selectedEmployeeId === null) {
                    break;
                }

                $available = 500 - $employeeStates[$selectedEmployeeId]['used_minutes'];
                $maxCount = floor($available / $tarification['minutes']);

                // Bir vaqtda taqsimlanadigan maksimal miqdorni cheklash
                $maxAssignAtOnce = ceil($tarificationLeft / max(1, count($assignedEmployeeIds) / 2));
                $assignCount = min($maxAssignAtOnce, $maxCount, $tarificationLeft);

                if ($assignCount <= 0) {
                    break; // Ko'proq ish taqsimlab bo'lmaydi
                }

                $assignMinutes = $assignCount * $tarification['minutes'];

                // Xodimning rejasiga qo'shish
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

                // Agar barcha xodimlar to'la bo'lsa, to'xtatish
                $allFull = true;
                foreach ($assignedEmployeeIds as $employeeId) {
                    if ($employeeStates[$employeeId]['used_minutes'] < 500) {
                        $allFull = false;
                        break;
                    }
                }

                if ($allFull) {
                    break;
                }
            }
        }

        // Rejalarni javob uchun tekislash
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