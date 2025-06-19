<?php

namespace App\Http\Controllers;

use App\Models\BoxTarification;
use App\Models\DailyPlan;
use App\Models\DailyPlanItem;
use App\Models\EmployeeTarificationLog;
use App\Models\Group;
use App\Models\Log;
use App\Models\Order;
use App\Models\OrderSubModel;
use App\Models\Tarification;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use App\Models\Employee;
use Svg\Tag\Rect;

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
        $user = auth()->user();

        // Faqat 'groupMaster' bo‘lsa va guruh tekshiruvi kerak bo‘lsa
        if ($user->role->name === 'groupMaster') {
            $userGroupId = $user->group->id ?? null;

            // Buyurtmaga tegishli submodellardan birortasi foydalanuvchining guruhiga tegishlimi?
            $matched = $order->orderModel->submodels->contains(function ($submodel) use ($userGroupId) {
                return optional($submodel->group)->group_id === $userGroupId;
            });

            if (!$matched) {
                return response()->json(['message' => '❌ Bu buyurtma sizning guruhingizga tegishli emas.'], 403);
            }
        }

        // Kerakli ma’lumotlarni yuklash
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
                'employee',
                'razryad',
                'typewriter',
            )
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($tarifications);
    }

    public function getTarifications(Request $request): \Illuminate\Http\JsonResponse
    {
            $region = $request->input('region');
            $submodelId = $request->input('submodel_id');

            // Region filterini TarificationCategory darajasida qo‘llaymiz
            $orderSubmodel = OrderSubModel::with([
                'orderModel.order',
                'tarificationCategories' => function ($query) use ($region) {
                    if ($region) {
                        $query->where('region', $region);
                    }
                },
                'tarificationCategories.tarifications' => function ($query) {
                    $query->with('tarificationLogs');
                },
                'tarificationCategories.tarifications.employee:id,name'
            ])->findOrFail($submodelId);

            $limit = $orderSubmodel->orderModel?->order?->quantity ?? 0;

            $tarifications = $orderSubmodel->tarificationCategories->flatMap(function ($category) use ($limit) {
                return $category->tarifications->map(function ($tarification) use ($limit) {
                    $totalQuantity = $tarification->tarificationLogs->sum('quantity');

                    return [
                        'id' => $tarification->id,
                        'name' => $tarification->name,
                        'code' => $tarification->code,
                        'second' => $tarification->second,
                        'summa' => $tarification->summa,
                        'employee_id' => $tarification->employee_id,
                        'employee_name' => $tarification->employee?->name,
                        'total_quantity' => $totalQuantity,
                        'limit' => $limit - $totalQuantity,
                    ];
                });
            });

            return response()->json([
                'tarifications' => $tarifications
            ]);
    }

    public function generateDailyPlan(Request $request): \Illuminate\Http\Response
    {
        $request->validate([
            'submodel_id' => 'required|exists:order_sub_models,id',
            'group_id' => 'required|exists:groups,id',
            'region' => 'nullable|string',
        ]);

        $region = $request->region;

        $submodel = OrderSubmodel::with([
            'tarificationCategories' => function ($query) use ($region) {
                $query->where('region', $region);
            },
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

        $counts = array_map(fn($p) => count($p['tarifications']), $plans);

        $maxCount = count($counts) > 0 ? max($counts) : 0;

// heightni hisoblash (masalan har bir tarification uchun 15mm desak)
        $heightPerTarification = 30; // mm
        $pageHeight = 80 + ($maxCount * $heightPerTarification); // mm

// Convert mm to points (1 mm ≈ 2.83465 points)
        $pageHeightPoints = $pageHeight * 2.83465;

// PDF hosil qilish
        $pdf = Pdf::loadView('pdf.daily-plan', [
            'plans' => $plans
        ])->setPaper([0, 0, 226.77, $pageHeightPoints], 'portrait'); // width: 80mm

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

    public function generateAttendanceNakladnoy(Request $request)
    {
        $groupId = $request->group_id;
        $date = $request->date ?? now()->toDateString();

        $employees = \App\Models\Employee::where('group_id', $groupId)
            ->whereHas('attendances', function ($q) use ($date) {
                $q->where('date', $date)->where('status', 'present');
            })
            ->with(['attendances' => fn($q) => $q->where('date', $date)])
            ->get();

        $plans = $employees->map(function ($employee) use ($date) {
            return [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'date' => $date,
            ];
        });

        dd($plans);

        $pdf = PDF::loadView('pdf.nakladnoy_blank', ['plans' => $plans])
            ->setPaper([0, 0, 226.77, 999.0], 'portrait');

        return $pdf->download('nakladnoy.pdf');
    }

    public function generateDailyPlanForOneEmployee(Request $request): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $request->validate([
            'submodel_id' => 'required|exists:order_sub_models,id',
            'employee_id' => 'required|exists:employees,id',
        ]);

        $submodel = OrderSubmodel::with([
            'tarificationCategories.tarifications' => function ($q) use ($request) {
                $q->whereHas('employee', function ($q2) use ($request) {
                    $q2->where('id', $request->employee_id);
                })->with('employee:id,name');
            }
        ])->findOrFail($request->submodel_id);

        $employee = Employee::findOrFail($request->employee_id);
        $group = Group::with('department')->findOrFail($employee->group_id);

        if (!$group) {
            return response()->json(['message' => 'Hodimning guruhi topilmadi, avval uni guruhga biriktirish kerak!'], 404);
        }

        $workStart = Carbon::parse($group->department->start_time);
        $workEnd = Carbon::parse($group->department->end_time);
        $breakTime = $group->department->break_time ?? 0;
        $totalWorkMinutes = $workEnd->diffInMinutes($workStart) - $breakTime;
        $date = now()->format('d-m-Y');

        $tasks = collect();
        foreach ($submodel->tarificationCategories as $category) {
            foreach ($category->tarifications as $tarification) {
                $tasks->push([
                    'id' => $tarification->id,
                    'code' => $tarification->code,
                    'name' => $tarification->name,
                    'seconds' => $tarification->second,
                    'sum' => $tarification->summa,
                    'minutes' => round($tarification->second / 60, 4),
                ]);
            }
        }

        $tasks = $tasks->sortBy('minutes')->values();
        $remainingMinutes = $totalWorkMinutes;
        $usedMinutes = 0;
        $totalEarned = 0;
        $assigned = [];

        if ($tasks->isEmpty()) {
            return response()->json([
                'message' => "❌ Xodim [{$employee->name}] uchun bironta ham tarifikatsiya topilmadi. Avval unga ish biriktiring!"
            ], 500);
        }

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

        foreach ($assigned as &$item) {
            unset($item['minutes_per_unit']);
        }

        $plan = DailyPlan::create([
            'employee_id' => $employee->id,
            'submodel_id' => $submodel->id,
            'group_id' => $group->id,
            'date' => Carbon::createFromFormat('d-m-Y', $date)->format('Y-m-d'),
            'used_minutes' => round($usedMinutes, 2),
            'total_earned' => round($totalEarned, 2),
        ]);

        $planItems = [];
        unset($item);
        foreach ($assigned as $item) {
            $planItems[] = [
                'daily_plan_id' => $plan->id,
                'tarification_id' => $item['tarification_id'],
                'count' => $item['count'],
                'total_minutes' => $item['total_minutes'],
                'amount_earned' => $item['amount_earned'],
            ];
        }

        DailyPlanItem::insert($planItems);

        $plans = [[
            'plan_id' => $plan->id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'used_minutes' => round($usedMinutes, 2),
            'total_minutes' => $totalWorkMinutes,
            'total_earned' => round($totalEarned, 2),
            'tarifications' => $assigned,
            'date' => $date,
        ]];

        $pdf = Pdf::loadView('pdf.daily-plan', ['plans' => $plans])
            ->setPaper([0, 0, 226.77, 566.93], 'portrait');

        Log::add(
            auth()->id(),
            "1 kishilik plan chiqarildi",
            'print',
            null,
            [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'submodel_name' => $submodel->submodel->name,
                'group_name' => $group->name,
                'date' => $date,
            ]
        );

        return $pdf->download('daily_plan_' . $employee->id . '.pdf');
    }

    public function showDailyPlan($id): \Illuminate\Http\JsonResponse
    {
        $dailyPlan = DailyPlan::with([
            'employee',
            'submodel.submodel',
            'group.department',
            'items.tarification.employee:id,name',
            'items.tarification.razryad:id,name',
            'items.tarification.typewriter:id,name',
        ])->findOrFail($id);

        // Tarificationlarni kod bo‘yicha sortlash
        $itemsArray = json_decode(json_encode($dailyPlan->items), true);
        usort($itemsArray, function ($a, $b) {
            $codeA = $a['tarification']['code'] ?? '';
            $codeB = $b['tarification']['code'] ?? '';

            preg_match('/^([A-Za-z]+)(\d+)$/', $codeA, $matchesA);
            preg_match('/^([A-Za-z]+)(\d+)$/', $codeB, $matchesB);

            if (!empty($matchesA) && !empty($matchesB)) {
                $letterCompare = strcmp(strtoupper($matchesA[1]), strtoupper($matchesB[1]));
                return $letterCompare !== 0 ? $letterCompare : ((int)$matchesA[2] - (int)$matchesB[2]);
            }

            return strcmp($codeA, $codeB);
        });
        $dailyPlan->setRelation('items', collect($itemsArray));

        // Ish vaqti hisoblash
        $department = $dailyPlan->group->department;
        $workStart = Carbon::parse($department->start_time);
        $workEnd = Carbon::parse($department->end_time);
        $breakTime = $department->break_time ?? 0;
        $dailyPlan->total_work_minutes = $workEnd->diffInMinutes($workStart) - $breakTime;

        // 🔁 Qo‘shimcha ishlar (rejadagi bo‘lmagan)
        $planTarificationIds = $dailyPlan->items->pluck('tarification_id')->toArray();
        $extraLogs = \App\Models\EmployeeTarificationLog::with([
            'tarification.employee:id,name',
            'tarification.razryad:id,name',
            'tarification.typewriter:id,name',
        ])
            ->where('employee_id', $dailyPlan->employee_id)
            ->whereDate('date', $dailyPlan->date)
            ->whereNotIn('tarification_id', $planTarificationIds)
            ->get();

        // Log
        Log::add(
            auth()->id(),
            "Plan ko'rsatildi",
            'print',
            null,
            [
                'submodel_name' => $dailyPlan->submodel->name,
                'group_name' => $dailyPlan->group->name,
                'date' => $dailyPlan->date,
            ]
        );

        return response()->json([
            'plan' => $dailyPlan,
            'extra_logs' => $extraLogs,
        ]);
    }

    public function employeeSalaryCalculation(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'tarifications' => 'required|array',
                'tarifications.*.id' => 'required|exists:tarifications,id',
                'tarifications.*.quantity' => 'required|numeric',
                'daily_plan_id' => 'required|exists:daily_plans,id',
            ]);

            $employee = Employee::findOrFail($request->employee_id);
            $dailyPlanId = $request->input('daily_plan_id');
            $tarifications = $request->input('tarifications');
            $oldBalance = $employee->balance;
            $today = now()->toDateString();

            if ($employee->status === 'kicked') {
                return response()->json(['message' => '❌ Xodim ishdan bo‘shatilgan.'], 403);
            }

            $dailyPlan = DailyPlan::findOrFail($dailyPlanId);
            $planAlreadySubmitted = (bool) $dailyPlan->status;
            $day = Carbon::parse($dailyPlan->date);

            $dailyPlan->update(['status' => true]);

            $employeeTarificationIds = $employee->tarifications()->pluck('id')->toArray();
            $totalEarned = 0;
            $totalDeducted = 0;

            foreach ($tarifications as $tarificationData) {
                $tarificationId = $tarificationData['id'];
                $quantity = $tarificationData['quantity'];

                $tarification = Tarification::with('tarificationCategory.submodel.orderModel.order')->findOrFail($tarificationId);
                $orderQuantity = $tarification->tarificationCategory->submodel->orderModel->order->quantity ?? 0;

                $existingItem = DailyPlanItem::where('daily_plan_id', $dailyPlanId)
                    ->where('tarification_id', $tarificationId)
                    ->first();

                $previousActual = ($planAlreadySubmitted && $existingItem) ? $existingItem->actual ?? 0 : 0;

                $alreadyDone = EmployeeTarificationLog::where('tarification_id', $tarificationId)->sum('quantity') - $previousActual;

                if (($alreadyDone + $quantity) > $orderQuantity) {
                    return response()->json([
                        'message' => "❌ [{$tarification->name}] uchun limitdan oshib ketdi. Ruxsat: $orderQuantity, bajarilgan: $alreadyDone, qo‘shilmoqchi: $quantity"
                    ], 422);
                }

                $isOwn = in_array($tarificationId, $employeeTarificationIds);
                $amount = $tarification->summa * $quantity;
                $oldAmount = $tarification->summa * $previousActual;

                EmployeeTarificationLog::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'tarification_id' => $tarificationId,
                        'date' => $day,
                    ],
                    [
                        'quantity' => $quantity,
                        'is_own' => $isOwn,
                        'amount_earned' => $amount
                    ]
                );

                if ($existingItem) {
                    $existingItem->update([
                        'actual' => $quantity,
                        'updated_at' => now()
                    ]);
                }

                $totalEarned += $amount;
                $totalDeducted += $oldAmount;
            }

            $balanceUpdated = false;
            if ($employee->payment_type === 'piece_work') {
                $diff = $totalEarned - ($planAlreadySubmitted ? $totalDeducted : 0);
                $employee->increment('balance', $diff);
                $balanceUpdated = true;
            }

            if (!$planAlreadySubmitted) {
                $dailyPlan->update(['status' => true]);
            }

            Log::add(
                auth()->id(),
                "Hisob-kitob " . ($planAlreadySubmitted ? 'yangilandi' : 'yaratildi'),
                'accounting',
                null,
                [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'old_balance' => $oldBalance,
                    'new_balance' => $employee->balance,
                    'total_earned' => $totalEarned,
                    'total_deducted' => $planAlreadySubmitted ? $totalDeducted : 0,
                ]
            );

            if ($employee->payment_type !== 'piece_work') {
                $message = "ℹ️ Xodim: {$employee->name} uchun hisob-kitob bajarildi, ammo to‘lov turi: `{$employee->payment_type}`.\nBalansga qo‘shilmadi.";
            } elseif ($planAlreadySubmitted) {
                $diff = round($totalEarned - $totalDeducted, 2);
                $message = "♻️ Reja yangilandi (qayta yuborildi).\nXodim: {$employee->name}\nOldin hisoblangan: " . number_format($totalDeducted, 0, ',', ' ') . " so'm\nYangi hisob: " . number_format($totalEarned, 0, ',', ' ') . " so'm\nBalans farqi: " . number_format($diff, 0, ',', ' ') . " so'm qo‘shildi.";
            } else {
                $message = "✅ Reja muvaffaqiyatli yuborildi.\nXodim: {$employee->name}\nUmumiy hisoblangan: " . number_format($totalEarned, 0, ',', ' ') . " so'm\nBalans yangilandi.";
            }

            return response()->json(['message' => $message]);
        } catch (\Exception $e) {
            Log::error("employeeSalaryCalculation xatoligi: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => "❌ Ichki xatolik yuz berdi. Iltimos, tizim adminiga murojaat qiling.",
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function showTarifications(Request $request): \Illuminate\Http\JsonResponse
    {
        $tarification = Tarification::where('code', $request->code)
            ->with(
                'employee:id,name',
                'razryad:id,name',
                'typewriter:id,name'
            )
            ->first();

        if (!$tarification) {
            return response()->json(['message' => 'Tarifikatsiya topilmadi'], 404);
        }

        return response()->json($tarification);
    }

    public function boxTarificationShow(BoxTarification $boxTarification, Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $boxTarification->load([
                'submodel.orderModel.order:id,name',
                'submodel.orderModel.model:id,name',
                'submodel.submodel:id,name',
                'tarification.razryad:id,name',
                'tarification.typewriter:id,name',
                'tarification.employee:id,name',
            ]);

            if ($boxTarification->status === 'completed') {
                $completedEmployee = EmployeeTarificationLog::where('box_tarification_id', $boxTarification->id)
                    ->first();

                return response()->json([
                    'box_tarification' => $boxTarification,
                    'employee' => [
                        'id' => $completedEmployee->employee->id,
                        'name' => $completedEmployee->employee->name,
                        'balance' => $completedEmployee->employee->balance,
                        'payment_type' => $completedEmployee->employee->payment_type,
                ],]);
            } elseif ($boxTarification->status === 'inactive') {
                return response()->json(['message' => '❌ Bu operatsiya bekor qilingan!'], 422);
            }

            $boxTarification->update(['status' => 'completed']);

            $isOwn = Tarification::where('user_id', $request->employee_id)
                ->where('id', $boxTarification->tarification_id)
                ->exists();

            EmployeeTarificationLog::create([
                'employee_id' => $request->employee_id,
                'tarification_id' => $boxTarification->tarification_id,
                'date' => now()->toDateString(),
                'quantity' => $boxTarification->quantity,
                'is_own' => $isOwn ? 1 : 0,
                'amount_earned' => $boxTarification->total,
                'box_tarification_id' => $boxTarification->id,
            ]);

            $employee = Employee::findOrFail($request->employee_id);
            $balance = $employee->balance;

            if ($employee->payment_type === 'piece_work') {
                $employee->increment('balance', $boxTarification->total);

                // Log
                Log::add(
                    auth()->id(),
                    "Kunlik maosh qo'shildi!",
                    'accounting',
                    null,
                    [
                        'employee_id' => $request->employee_id,
                        'employee_name' => $employee->name,
                        'tarification_id' => $boxTarification->tarification_id,
                        'tarification_name' => $boxTarification->tarification->name,
                        'old_balance' => $balance,
                        'new_balance' => $employee->balance,
                        'total_earned' => $boxTarification->total,
                    ]
                );

                return response()->json([
                        'box_tarification' => $boxTarification,
                        'employee' => [
                            'id' => $employee->id,
                            'name' => $employee->name,
                            'balance' => $employee->balance,
                            'payment_type' => $employee->payment_type,
                        ],
                ]);
            }

            return response()->json([
                'message' => "ℹ️ Xodim: {$employee->name} uchun hisob-kitob bajarildi, ammo to‘lov turi: `{$employee->payment_type}`.\nBalansga qo‘shilmadi.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '❌ Ichki xatolik yuz berdi. Iltimos, tizim adminiga murojaat qiling.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function getEmployeeByGroupID(): \Illuminate\Http\JsonResponse
    {
        $groupId = request()->input('group_id');
        $employees = Employee::where('group_id', $groupId)
            ->get();

        return response()->json($employees);
    }

    public function salaryCalculation(Request $request){
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'tarifications' => 'required|array',
            'tarifications.*.id' => 'required|exists:tarifications,id',
            'tarifications.*.quantity' => 'required|numeric',
        ]);

        $employee = Employee::findOrFail($request->employee_id);

        if ($employee->status === 'kicked') {
            return response()->json(['message' => '❌ Xodim ishdan bo‘shatilgan.'], 403);
        }

        foreach ($request->tarifications as $tarificationData) {
            $tarification = Tarification::findOrFail($tarificationData['id']);
            $quantity = $tarificationData['quantity'];

            // Hisob-kitob logini yaratish
            EmployeeTarificationLog::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'tarification_id' => $tarification->id,
                    'date' => now()->toDateString(),
                ],
                [
                    'quantity' => $quantity,
                    'is_own' => in_array($tarification->id, $employee->tarifications->pluck('id')->toArray()),
                    'amount_earned' => $tarification->summa * $quantity,
                ]
            );

            // Agar xodimning to'lov turi "piece_work" bo'lsa, balansni yangilash

            if ($employee->payment_type === 'piece_work') {
                $amountEarned = $tarification->summa * $quantity;
                $employee->increment('balance', $amountEarned);
            }
            // Log yozish
            Log::add(
                auth()->id(),
                "Hisob-kitob amalga oshirildi",
                'accounting',
                null,
                [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'tarification_id' => $tarification->id,
                    'tarification_name' => $tarification->name,
                    'quantity' => $quantity,
                    'amount_earned' => $tarification->summa * $quantity,
                ]
            );
        }
    }

    public function getEmployeeTarificationLog(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderSubmodel = OrderSubModel::where('id', $request->submodel_id)
        ->whereHas('tarificationCategories', function ($query) use ($request) {
            $query->where('region', $request->region);
        })->first();
        
        if (!$orderSubmodel) {
            return response()->json([
                'error'=> 'Order submodel not found or does not match the region.',
                ]);

        }

        $tarifications = Tarification::whereHas('tarificationCategory', function ($query) use ($orderSubmodel) {
            $query->where('submodel_id', $orderSubmodel->id);
        })->whereHas('tarificationCategory', function ($query) use ($request) {
            $query->where('region', $request->region);
        })->with('tarificationLogs')->get();


        if ($tarifications->isEmpty()) {
            return response()->json([
                'message' => '❌ Tarifikatsiyalar topilmadi.',
            ], 404);
        }

        $logs = $tarifications->flatMap(function ($tarification) {
            return $tarification->tarificationLogs->map(function ($log) use ($tarification) {
                return [
                    'id' => $log->id,
                    'employee' => $log->employee,
                    'tarification' => [
                        'id' => $tarification->id,
                        'name' => $tarification->name,
                        'code' => $tarification->code,
                        'second' => $tarification->second,
                        'summa' => $tarification->summa,
                    ],
                    'date' => $log->date,
                    'quantity' => $log->quantity,
                    'is_own' => $log->is_own,
                    'amount_earned' => $log->amount_earned,
                    'box_tarification' => $log->box_tarification_id ? [
                        'id' => $log->box_tarification_id,
                        'quantity' => $log->box_tarification_quantity,
                        'total' => $log->box_tarification_total,
                    ] : null,
                ];
            });
        });

        return response()->json($logs->values() ?? [],200);


    }
}