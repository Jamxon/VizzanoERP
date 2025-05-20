<?php

namespace App\Http\Controllers;

use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\Group;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderSubModel;
use App\Models\Tarification;
use App\Models\TarificationCategory;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;


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
                'orderModel.submodels.group.group',
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
            'orderModel.submodels.group.group',
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

    public function generateDailyPlan(Request $request): \Illuminate\Http\Response
    {
        $request->validate([
            'submodel_id' => 'required|exists:order_sub_models,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        // Kerakli ma'lumotlarni bir so'rov bilan olish
        $submodel = OrderSubmodel::with([
            'tarificationCategories.tarifications.employee:id,name'
        ])->findOrFail($request->submodel_id);

        $group = Group::with('department')->findOrFail($request->group_id);

        // Ish vaqtini hisoblash
        $workStart = Carbon::parse($group->department->start_time);
        $workEnd = Carbon::parse($group->department->end_time);
        $breakTime = $group->department->break_time ?? 0;
        $totalWorkMinutes = $workEnd->diffInMinutes($workStart) - $breakTime;
        $totalMinutes = $workEnd->diffInMinutes($workStart);

        $plans = [];
        $employeeTarifications = [];
        $date = now()->format('d-m-Y');

        // Tarifikatsiya ma'lumotlarini bitta tsiklda to'plash
        foreach ($submodel->tarificationCategories as $category) {
            foreach ($category->tarifications as $tarification) {
                if ($tarification->employee) {
                    $employeeId = $tarification->employee->id;

                    if (!isset($employeeTarifications[$employeeId])) {
                        $employeeTarifications[$employeeId] = [
                            'name' => $tarification->employee->name,
                            'tasks' => []
                        ];
                    }

                    $employeeTarifications[$employeeId]['tasks'][] = [
                        'id' => $tarification->id,
                        'code' => $tarification->code,
                        'name' => $tarification->name,
                        'seconds' => $tarification->second,
                        'sum' => $tarification->summa,
                        'minutes' => round($tarification->second / 60, 4),
                    ];
                }
            }
        }

        // Har bir xodim uchun plan tuzish
        foreach ($employeeTarifications as $employeeId => $employeeData) {
            $employeeName = $employeeData['name'];
            $tasks = collect($employeeData['tasks'])->sortBy('minutes');

            $remainingMinutes = $totalWorkMinutes;
            $usedMinutes = 0;
            $totalEarned = 0;
            $assigned = [];

            // Dastlabki vazifalarni belgilash (har bir vazifadan kamida bitta)
            foreach ($tasks as $task) {
                if ($remainingMinutes >= $task['minutes']) {
                    $count = 1;
                    $total_minutes = round($task['minutes'], 2);
                    $amount_earned = round($task['sum'], 2);

                    $assigned[] = [
                        'tarification_id' => $task['id'],
                        'tarification_name' => $task['name'],
                        'code' => $task['code'],
                        'seconds' => $task['seconds'],
                        'count' => $count,
                        'minutes_per_unit' => $task['minutes'],
                        'total_minutes' => $total_minutes,
                        'sum' => $task['sum'],
                        'amount_earned' => $amount_earned,
                    ];

                    $usedMinutes += $task['minutes'];
                    $remainingMinutes -= $task['minutes'];
                    $totalEarned += $amount_earned;
                }
            }

            // Qolgan vaqtni to'ldirish
            $tasksCount = count($assigned);
            if ($tasksCount > 0) {
                $i = 0;
                while ($remainingMinutes > 0) {
                    $index = $i % $tasksCount;
                    $item = &$assigned[$index];
                    $minutes = $item['minutes_per_unit'];

                    if ($remainingMinutes >= $minutes) {
                        $item['count'] += 1;
                        $item['total_minutes'] = round($item['count'] * $minutes, 2);
                        $item['amount_earned'] = round($item['count'] * $item['sum'], 2);

                        $usedMinutes += $minutes;
                        $remainingMinutes -= $minutes;
                        $totalEarned += $item['sum'];
                    } else {
                        break;
                    }

                    $i++;
                }
            }

            // Plan DB ga saqlash va uning elementlarini tayyorlash
            $planItems = [];
            foreach ($assigned as &$item) {
                $planItems[] = [
                    'tarification_id' => $item['tarification_id'],
                    'count' => $item['count'],
                    'total_minutes' => $item['total_minutes'],
                    'amount_earned' => $item['amount_earned'],
                ];

                // UI uchun keraksiz maydonni olib tashlash
                unset($item['minutes_per_unit']);
            }

            // Yangi plan yaratish
            $plan = DailyPlan::create([
                'employee_id' => $employeeId,
                'submodel_id' => $submodel->id,
                'group_id' => $group->id,
                'date' =>  Carbon::createFromFormat('d-m-Y', $date)->format('Y-m-d'),
                'used_minutes' => round($usedMinutes, 2),
                'total_earned' => round($totalEarned, 2),
            ]);

            // Plan elementlarini bir so'rov bilan saqlash
            if (!empty($planItems)) {
                foreach ($planItems as &$item) {
                    $item['daily_plan_id'] = $plan->id;
                }
                DailyPlanItem::insert($planItems);
            }

            // Yakuniy plan ma'lumotlarini yig'ish
            $plans[] = [
                'plan_id' => $plan->id,
                'employee_id' => $employeeId,
                'employee_name' => $employeeName,
                'used_minutes' => round($usedMinutes, 2),
                'total_minutes' => $totalWorkMinutes,
                'total_earned' => round($totalEarned, 2),
                'tarifications' => $assigned,
                'date' => $date,
            ];
        }

        // PDF yaratish va yuklash
        $pdf = Pdf::loadView('pdf.daily-plan', [
            'plans' => $plans
        ])->setPaper([0, 0, 226.77, 566.93], 'portrait'); // 80mm x ~200mm

        Log::add(
            auth()->id(),
            "Plan chiqarildi",
            'print',
            null,
            [
                'submodel_name' => $submodel->submodel->name,
                'group_name' => $group->name,
                'date' => $date,
            ]
        );

        return $pdf->download('daily_plan.pdf');
    }

    public function showDailyPlan(DailyPlan $dailyPlan): \Illuminate\Http\JsonResponse
    {
        $dailyPlan->load(
            'employee',
            'submodel.submodel',
            'group',
            'items.tarification',
            'items.tarification.employee',
            'items.tarification.razryad',
            'items.tarification.typewriter'
        );

        Log::add(
            auth()->id(),
            "Plan chiqarildi",
            'print',
            null,
            [
                'submodel_name' => $dailyPlan->submodel->name,
                'group_name' => $dailyPlan->group->name,
                'date' => $dailyPlan->date,
            ]
        );

        return response()->json($dailyPlan);
    }

    public function employeeSalaryCalculation(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'tarifications' => 'required|array',
            'tarifications.*.id' => 'required|exists:tarifications,id',
            'tarifications.*.quantity' => 'required|numeric|min:1',
        ]);

        $employeeId = $request->input('employee_id');
        $tarifications = $request->input('tarifications');
        $employee = Employee::findOrFail($employeeId);
        $employeeTarificationIds = $employee->tarifications()->pluck('id')->toArray();

        $totalEarned = 0;
        $today = now()->toDateString();

        foreach ($tarifications as $tarificationData) {
            $tarificationId = $tarificationData['id'];
            $quantity = $tarificationData['quantity'];

            $tarification = \App\Models\Tarification::with('tarificationCategory.orderSubmodel')->findOrFail($tarificationId);
            $orderQuantity = $tarification->tarificationCategory->submodel->orderModel->order->quantity;

            // Hozirgacha shu tarification bo‘yicha jami bajarilgan soni
            $totalDone = \App\Models\EmployeeTarificationLog::where('tarification_id', $tarificationId)->sum('quantity');

            // Limit tekshiruvi
            if (($totalDone + $quantity) > $orderQuantity) {
                return response()->json([
                    'message' => "❌ Tarification [{$tarification->name}] uchun limitdan oshib ketdi. Jami ruxsat etilgan: $orderQuantity, bajarilgan: $totalDone, qo‘shilmoqchi: $quantity"
                ], 422);
            }

            $amount = $tarification->summa * $quantity;
            $isOwn = in_array($tarificationId, $employeeTarificationIds);

            // Log yozish
            \App\Models\EmployeeTarificationLog::create([
                'employee_id' => $employee->id,
                'tarification_id' => $tarificationId,
                'date' => $today,
                'quantity' => $quantity,
                'is_own' => $isOwn,
                'amount_earned' => $amount,
            ]);

            $totalEarned += $amount;
        }

        // Foydalanuvchi balansini yangilash
        $employee->increment('balance', $totalEarned);

        return response()->json([
            'message' => '✅ Ishchi balansi muvaffaqiyatli yangilandi',
            'total_earned' => $totalEarned,
        ]);
    }



}