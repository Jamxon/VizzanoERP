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

        $groupId = $request->input('group_id');
        $submodelId = $request->input('submodel_id');

        $group = Group::with('employees')->findOrFail($groupId);
        $employees = $group->employees;

        if ($employees->isEmpty()) {
            return response()->json(['message' => 'Guruhda hodimlar mavjud emas'], 400);
        }

        $submodel = OrderSubmodel::with('tarificationCategories.tarifications')->findOrFail($submodelId);

        // Tarificationlarni yig‘amiz
        $tarifications = collect();
        foreach ($submodel->tarificationCategories as $category) {
            foreach ($category->tarifications as $tarification) {
                if ($tarification->seconds > 0) {
                    $tarifications->push([
                        'id' => $tarification->id,
                        'name' => $tarification->name,
                        'seconds' => $tarification->seconds,
                        'minutes' => $tarification->seconds / 60,
                    ]);
                }
            }
        }

        if ($tarifications->isEmpty()) {
            return response()->json(['message' => 'Tarificationlar topilmadi'], 400);
        }

        $plans = [];
        $employeeCount = $employees->count();
        $employeeStates = [];

        // Har bir employee uchun ishlatilgan minutni 0 qilib boshlaymiz
        foreach ($employees as $employee) {
            $employeeStates[$employee->id] = [
                'used_minutes' => 0,
                'name' => $employee->name ?? 'No name',
            ];
        }

        $currentEmployeeIndex = 0;
        foreach ($tarifications as $tarification) {
            $tarificationLeft = 999999; // shunchaki katta son — sizda bo‘lishi mumkin bo‘lgan maksimal birlik soni
            while ($tarificationLeft > 0) {
                $employee = $employees[$currentEmployeeIndex];
                $employeeId = $employee->id;

                $used = $employeeStates[$employeeId]['used_minutes'];
                $available = 500 - $used;

                if ($available <= 0) {
                    // Keyingi employee
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
                    'total_minutes' => $assignMinutes,
                ];

                // Yangilash
                $employeeStates[$employeeId]['used_minutes'] += $assignMinutes;
                $tarificationLeft -= $assignCount;

                // Keyingi employee ga o‘tish
                $currentEmployeeIndex = ($currentEmployeeIndex + 1) % $employeeCount;

                // Agar hamma hodimning limiti to‘lgan bo‘lsa — break
                $allFull = collect($employeeStates)->every(fn($e) => $e['used_minutes'] >= 500);
                if ($allFull) break 2;
            }
        }

        return response()->json([
            'message' => 'Kunlik plan yaratildi',
            'data' => $plans,
        ]);
    }

}