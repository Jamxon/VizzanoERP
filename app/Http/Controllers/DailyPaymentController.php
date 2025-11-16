<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderModel;
use App\Models\SewingOutputs;
use Illuminate\Http\Request;
use App\Models\DailyPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailyPaymentController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()->employee->branch_id ?? null;
        $usdRate = getUsdRate();

        $selectedMonth = $request->month ?? now()->format('Y-m');
        $selectedSeasonYear = $request->season_year;
        $selectedSeasonType = $request->season_type;

        $modelData = Order::query()
            ->with([
                'orderModel.submodels.submodel',
                'orderModel.submodels.group.group.responsibleUser',
                'monthlySelectedOrder',
                'dailyPayments' => function ($q) use ($branchId) {
                    $q->whereHas('employee', fn($q2) => $q2->where('branch_id', $branchId));
                }
            ])
            ->whereHas('monthlySelectedOrder', function ($q) use ($selectedMonth) {
                $q->whereMonth('month', date('m', strtotime($selectedMonth)))
                    ->whereYear('month', date('Y', strtotime($selectedMonth)));
            })
            ->where('branch_id', $branchId)
            ->when($selectedSeasonYear, fn($q) => $q->where('season_year', $selectedSeasonYear)
            )
            ->when($selectedSeasonType, fn($q) => $q->where('season_type', $selectedSeasonType)
            )
            ->get()
            ->flatMap(function ($order) use ($usdRate, $branchId) {
                return collect($order->orderModel ? [$order->orderModel] : [])->map(function ($om) use ($order, $usdRate, $branchId) {
                    $model = $om->model;

                    // ✅ Worker costs (aggregated even if zero)
                    $workerCost = $order->dailyPayments
                        ->where('model_id', $model->id)
                        ->sum('calculated_amount');

                    // ✅ Produced quantity
                    $produced = SewingOutputs::join('order_sub_models', 'order_sub_models.id', '=', 'sewing_outputs.order_submodel_id')
                        ->join('order_models', 'order_models.id', '=', 'order_sub_models.order_model_id')
                        ->where('order_models.order_id', $order->id)
                        ->where('order_models.model_id', $model->id)
                        ->sum('sewing_outputs.quantity');

                    $minutes = $produced * ($model->minute ?? 0);

                    // ✅ Department Real Costs (maybe zero)
                    $departmentRealCosts = DailyPayment::select(
                        'department_id',
                        DB::raw('SUM(calculated_amount + bonus) as cost')
                    )
                        ->with('department:id,name')
                        ->where('order_id', $order->id)
                        ->where('model_id', $model->id)
                        ->whereNotNull('department_id')
                        ->groupBy('department_id')
                        ->get()
                        ->map(fn($d) => [
                            'id' => $d->department_id,
                            'name' => $d->department?->name,
                            'cost' => round($d->cost, 2)
                        ]);

                    $departmentRealTotal = $departmentRealCosts->sum('cost');

                    // ✅ Planned Department Costs (always exist)
                    $plannedDepartmentCosts = DepartmentBudget::with('department:id,name')
                        ->whereHas('department.mainDepartment', fn($q) => $q->where('branch_id', $branchId))
                        ->get()
                        ->map(function ($db) use ($order, $model, $usdRate) {
                            if ($db->type === 'minute_based') {
                                $planned = $db->quantity * ($model->minute ?? 0) * $order->quantity;
                            } elseif ($db->type === 'percentage_based') {
                                $priceUzs = ($order->price ?? 0) * $usdRate;
                                $planned = $priceUzs * ($db->quantity / 100) * ($order->quantity ?? 0);
                            } else {
                                $planned = 0;
                            }

                            return [
                                'id' => $db->department_id,
                                'name' => $db->department?->name,
                                'planned_cost' => round($planned, 2)
                            ];
                        });

                    $plannedDepartmentTotal = $plannedDepartmentCosts->sum('planned_cost');

                    // ✅ Expense costs always exist
                    $expenses = Expense::where('branch_id', $branchId)->get()->map(function ($exp) use ($order, $model, $usdRate, $produced) {
                        $minutes = $produced * ($model->minute ?? 0);
                        $priceUzs = ($order->price ?? 0) * $usdRate;

                        if ($exp->type === 'minute_based') {
                            $real_cost = $exp->quantity * $minutes;
                            $planned_cost = $exp->quantity * ($model->minute ?? 0) * $order->quantity;
                        } elseif ($exp->type === 'percentage_based') {
                            $real_cost = $priceUzs * ($exp->quantity / 100) * $produced;
                            $planned_cost = $priceUzs * ($exp->quantity / 100) * $order->quantity;
                        } else {
                            $real_cost = 0;
                            $planned_cost = 0;
                        }

                        return [
                            'id' => $exp->id,
                            'name' => $exp->name,
                            'type' => $exp->type,
                            'quantity' => $exp->quantity,
                            'real_cost' => round($real_cost, 2),
                            'planned_cost' => round($planned_cost, 2)
                        ];
                    });

                    $expensesRealTotal = $expenses->sum('real_cost');
                    $plannedExpensesTotal = $expenses->sum('planned_cost');

                    return [
                        "order" => $order,
                        "model" => [
                            'id' => $model->id,
                            'name' => $model->name,
                            'minute' => $model->minute,
                        ],
                        'rasxod' => $order->orderModel->rasxod ?? 0,
                        "produced_quantity" => $produced,
                        "minutes" => $minutes,
                        "worker_real_cost" => round($workerCost, 2),
                        "department_real_costs" => $departmentRealCosts,
                        "expenses_real_costs" => $expenses,
                        "total_real_cost" => round($workerCost + $departmentRealTotal + $expensesRealTotal, 2),
                        "planned_costs" => [
                            "department" => [
                                "total" => $plannedDepartmentTotal,
                                "details" => $plannedDepartmentCosts
                            ],
                            "expenses" => [
                                "total" => $plannedExpensesTotal,
                                "details" => $expenses
                            ],
                            "total_planned_cost" => round($plannedDepartmentTotal + $plannedExpensesTotal, 2),
                        ]
                    ];
                });
            })
            ->values();

        return response()->json($modelData);
    }

    public function getDepartmentsWithBudgetsAndEmployeeCount(Request $request): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()->employee->branch_id ?? null;

        $start = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : null;
        $end = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : null;

        // ✅ Attendance counts with correct date filtering
        $attendanceCounts = DB::table('attendances')
            ->select('employee_id', DB::raw('COUNT(*) as present_count'))
            ->whereIn('employee_id', function ($q) use ($branchId) {
                $q->select('id')->from('employees')->where('branch_id', $branchId);
            })
            ->where(function ($q) {
                $q->where('status', 'present')->orWhere('status', 1);
            })
            ->when($start, fn($q) => $q->whereDate('date', '>=', $start))
            ->when($end, fn($q) => $q->whereDate('date', '<=', $end))
            ->groupBy('employee_id')
            ->pluck('present_count', 'employee_id');

        $departments = Department::whereHas('mainDepartment', fn($q) => $q->where('branch_id', $branchId))
            ->with('departmentBudget')
            ->withCount('employees')
            ->with(['employees' => function ($q) {
                $q->select('id', 'name', 'phone', 'department_id', 'percentage', 'position_id')
                    ->with('position:id,name');
            }])
            ->get()
            ->map(function ($department) use ($attendanceCounts) {

                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'employee_count' => $department->employees_count,
                    'budget' => $department->departmentBudget,
                    'employees' => $department->employees->map(function ($e) use ($attendanceCounts) {
                        return [
                            'id' => $e->id,
                            'name' => $e->name,
                            'phone' => $e->phone,
                            'position' => $e->position,
                            'percentage' => $e->percentage,
                            'attendance_present_count' => $attendanceCounts[$e->id] ?? 0,
                        ];
                    })->values(),
                ];
            });

        return response()->json($departments);
    }

    public function getDepartmentPayments(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'department_id' => 'required|integer|exists:departments,id',
            'order_id' => 'nullable|integer|exists:orders,id',
        ]);

        $branchId = auth()->user()->employee->branch_id ?? null;

        $selectedSeasonYear = $request->season_year;
        $selectedSeasonType = $request->season_type;
        $departmentId = $request->department_id;
        $orderId = $request->order_id ?? null;

        $data = DailyPayment::select(
            'id',
            'employee_id',
            'model_id',
            'order_id',
            'department_id',
            'quantity_produced',
            'calculated_amount',
            'employee_percentage',
            'payment_date'
        )
            ->with([
                'employee:id,name',
                'model:id,name',
                'order:id,name,season_year,season_type'
            ])
            ->where('department_id', $departmentId)
            ->whereHas('employee', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
                $q->where('status', 'working');
            })
            ->whereHas('order', function ($q) use ($selectedSeasonYear, $selectedSeasonType) {
                if ($selectedSeasonYear) {
                    $q->where('season_year', $selectedSeasonYear);
                }
                if ($selectedSeasonType) {
                    $q->where('season_type', $selectedSeasonType);
                }
            })
            ->when($orderId, fn($q) => $q->where('order_id', $orderId))
            ->where('calculated_amount', '>', 0)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($row) {


                return [
                    'id' => $row->id,
                    'employee' => [
                        'id' => $row->employee_id,
                        'name' => $row->employee?->name,
                    ],
                    'order' => [
                        'id' => $row->order_id,
                        'name' => $row->order?->name,
                        'season_year' => $row->order?->season_year,
                        'season_type' => $row->order?->season_type,
                    ],
                    'model' => [
                        'id' => $row->model_id,
                        'name' => $row->model?->name,
                    ],
                    'department_id' => $row->department_id,
                    'quantity_produced' => $row->quantity_produced,
                    'calculated_amount' => round($row->calculated_amount, 2),
                    'employee_percentage' => round($row->employee_percentage, 2),
                    'payment_date' => $row->payment_date
                ];
            });

        return response()->json($data);
    }

    public function show(Department $department, Request $request): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()->employee->branch_id ?? null;

        if ($department->mainDepartment->branch_id !== $branchId) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        $department->load([
            'departmentBudget',
            'employees' => function ($q) {
                $q->where('status', 'working')
                    ->select('id', 'name', 'phone', 'department_id', 'percentage', 'position_id', 'img', 'branch_id', 'salary', 'payment_type')
                    ->with('position:id,name');
            }
        ]);
        $month = $request->month ? Carbon::parse($request->month)->format('m') : now()->format('m');
        $year = $request->month ? Carbon::parse($request->month)->format('Y') : now()->format('Y');

        $usdRate = getUsdRate();
        $seasonYear = 2026;
        $seasonType = 'summer';

        $departmentTotal = [
            'total_earned' => 0,
            'total_remaining' => 0,
            'total_possible' => 0,
            'total_possible_season' => 0,
        ];

        $startOfMonth = Carbon::create($year, $month, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();
        $totalWorkingDays = 0;

        for ($dateIter = $startOfMonth->copy(); $dateIter <= $endOfMonth; $dateIter->addDay()) {
            if (!$dateIter->isSunday()) {
                $totalWorkingDays++;
            }
        }

        $employeesData = $department->employees->map(function ($employee) use ($totalWorkingDays, $year, $month, $request, $usdRate, $seasonYear, $seasonType, $departmentTotal) {
            $branchId = $employee->branch_id;
            $empPercent = floatval($employee->percentage ?? 0);

            // --- Monthly orders for this employee

            $presentDays = Attendance::where('employee_id', $employee->id)
                ->where('status', 'present')
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->count();

            $data = DB::table('orders')
                ->select(
                    'orders.id as order_id',
                    'orders.name as order_name',
                    'orders.quantity as planned_quantity',
                    'order_models.model_id',
                    'models.name as model_name',
                    'models.minute as model_minute',
                    DB::raw("
                        COALESCE(
                            SUM(
                                COALESCE(daily_payments.calculated_amount, 0)
                                + COALESCE(daily_payments.bonus, 0)
                            ),
                        0) AS earned_amount
                    "),
                    DB::raw("COALESCE(SUM(daily_payments.quantity_produced),0) as produced_quantity"),
                    DB::raw("MAX(daily_payments.department_id) as department_id"),
                    'orders.price'
                )
                ->join('order_models', 'order_models.order_id', '=', 'orders.id')
                ->join('models', 'models.id', '=', 'order_models.model_id')
                ->leftJoin('daily_payments', function ($q) use ($employee, $month, $year) {
                    $q->on('daily_payments.order_id', '=', 'orders.id')
                        ->where('daily_payments.employee_id', '=', $employee->id)
                        ->whereMonth('daily_payments.payment_date', $month)
                        ->whereYear('daily_payments.payment_date', $year);
                })
                ->leftJoin('monthly_selected_orders', function ($q) use ($month, $year) {
                    $q->on('monthly_selected_orders.order_id', '=', 'orders.id')
                        ->whereMonth('monthly_selected_orders.month', $month)
                        ->whereYear('monthly_selected_orders.month', $year);
                })
                ->where('orders.branch_id', $branchId)
                ->whereExists(function ($query) use ($month, $year) {
                    $query->select(DB::raw(1))
                        ->from('monthly_selected_orders')
                        ->whereColumn('monthly_selected_orders.order_id', 'orders.id')
                        ->whereMonth('monthly_selected_orders.month', $month)
                        ->whereYear('monthly_selected_orders.month', $year);
                })
                ->groupBy(
                    'orders.id',
                    'order_models.model_id',
                    'models.name',
                    'models.minute',
                    'orders.name',
                    'orders.quantity',
                    'orders.price'
                )
                ->get();

            $orders = $data->map(function ($row) use ($employee, $usdRate, $empPercent, $month, $year) {
                $departmentBudget = DB::table('department_budgets')->where('department_id', $employee->department_id)->first();

                $perPieceEarn = 0;
                if ($departmentBudget && $departmentBudget->quantity > 0) {
                    if ($departmentBudget->type === 'minute_based') {
                        $perPieceEarn = $row->model_minute * $departmentBudget->quantity / 100 * $empPercent;
                    } elseif ($departmentBudget->type === 'percentage_based') {
                        $priceUzs = ($row->price ?? 0) * $usdRate;
                        $perPieceEarn = (($priceUzs * $departmentBudget->quantity) / 100) * ($empPercent / 100);
                    }
                }

                $remainingQuantity = max($row->planned_quantity - $row->produced_quantity, 0);

                return [
                    "order" => [
                        "id" => $row->order_id,
                        "name" => $row->order_name,
                        'minute' => $row->model_minute,
                    ],
                    "planned_quantity" => $row->planned_quantity,
                    "produced_quantity" => $row->produced_quantity,
                    "remaining_quantity" => $remainingQuantity,
                    "earned_amount" => round($row->earned_amount, 2),
                    "remaining_earn_amount" => round($remainingQuantity * $perPieceEarn, 2),
                    "possible_full_earn_amount" => round($row->planned_quantity * $perPieceEarn, 2),
                ];
            });

            $monthlyTotal = [
                'total_planned_quantity' => $orders->sum('planned_quantity'),
                'total_earned' => round($orders->sum('earned_amount'), 2),
                'total_remaining' => round($orders->sum('remaining_earn_amount'), 2),
                'total_possible' => round($orders->sum('possible_full_earn_amount'), 2),
            ];

            // --- Season orders for this employee
            $seasonOrders = DB::table('orders')
                ->select('orders.id', 'orders.quantity', 'order_models.model_id', 'models.minute', 'orders.price')
                ->join('order_models', 'order_models.order_id', '=', 'orders.id')
                ->join('models', 'models.id', '=', 'order_models.model_id')
                ->where('orders.branch_id', $employee->branch_id)
                ->where('orders.season_year', $seasonYear)
                ->where('orders.season_type', $seasonType)
                ->get();

            $totalPossibleSeason = 0;
            foreach ($seasonOrders as $row) {
                $departmentBudget = DB::table('department_budgets')->where('department_id', $employee->department_id)->first();
                if (!$departmentBudget || $departmentBudget->quantity <= 0) continue;

                $perPieceEarn = 0;
                if ($departmentBudget->type === 'minute_based') {
                    $perPieceEarn = $row->minute * $departmentBudget->quantity / 100 * $empPercent;
                } elseif ($departmentBudget->type === 'percentage_based') {
                    $priceUzs = ($row->price ?? 0) * $usdRate;
                    $perPieceEarn = (($priceUzs * $departmentBudget->quantity) / 100) * ($empPercent / 100);
                }
                $totalPossibleSeason += $row->quantity * $perPieceEarn;
            }

            $monthlyTotal['total_possible_season'] = round($totalPossibleSeason, 2);

            $salary = match ($employee->payment_type) {
                'monthly' => $employee->salary,
                'daily' => $employee->salary * 26,
                'hourly' => $employee->salary * 260,
                default => $employee->salary,
            };

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'percentage' => $employee->percentage,
                'position' => $employee->position,
                'img' => $employee->img,
                'payment_type' => $employee->payment_type,
                'salary' => $salary,
                'attendance' => [
                    'present_days' => $presentDays,
                    'total_working_days' => $totalWorkingDays,
                ],
                'orders' => $orders,
                'totals' => $monthlyTotal,
            ];
        });

        // --- Department totals
        $departmentTotals = [
            'total_earned' => round($employeesData->sum(fn($e) => $e['totals']['total_earned']), 2),
            'total_remaining' => round($employeesData->sum(fn($e) => $e['totals']['total_remaining']), 2),
            'total_possible' => round($employeesData->sum(fn($e) => $e['totals']['total_possible']), 2),
            'total_possible_season' => round($employeesData->sum(fn($e) => $e['totals']['total_possible_season']), 2),
        ];

        return response()->json([
            'id' => $department->id,
            'name' => $department->name,
            'budget' => $department->departmentBudget ? [
                'id' => $department->departmentBudget->id,
                'quantity' => $department->departmentBudget->quantity,
                'type' => $department->departmentBudget->type,
            ] : null,
            'employee_count' => $department->employees->count(),
            'employees' => $employeesData,
            'department_totals' => $departmentTotals,
        ]);
    }

    public function storeDepartmentBudget(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'department_id' => 'required|integer|exists:departments,id',
            'quantity' => 'required|numeric|min:0',
            'type' => 'required|string|in:minute_based,percentage_based',
        ]);

        $branchId = auth()->user()->employee->branch_id;

        $department = Department::where('id', $validated['department_id'])
            ->whereHas('mainDepartment', fn($q) => $q->where('branch_id', $branchId))
            ->firstOrFail();

        // ✅ Duplicate-check
        if ($department->departmentBudget) {
            return response()->json(['message' => 'Budget already exists for this department.'], 409);
        }

        $department->departmentBudget()->create([
            'quantity' => $validated['quantity'],
            'type' => $validated['type'],
        ]);

        return response()->json(['message' => 'Department budget saved successfully.'], 201);
    }

    public function editDepartmentBudget(Request $request, DepartmentBudget $departmentBudget): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()->employee->branch_id;

        // Branch security
        if ($departmentBudget->department->mainDepartment->branch_id !== $branchId) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        // Validate input (only fields provided will be validated)
        $validated = $request->validate([
            'quantity' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|string|in:minute_based,percentage_based',
        ]);

        // Use transaction for safety
        DB::beginTransaction();
        try {
            // Update only provided fields
            $departmentBudget->update($validated);

            // Recalculate daily_payments for this department
            $departmentId = $departmentBudget->department_id;
            $usdRate = getUsdRate();

            // Get all daily_payments for this department (could be many)
            // We select fields needed to compute: id, employee_percentage, order_id, model_id, payment_date
            $payments = DailyPayment::select(
                'id',
                'employee_id',
                'order_id',
                'model_id',
                'payment_date',
                'quantity_produced',
                'employee_percentage'
            )
                ->where('department_id', $departmentId)
                ->get();

            if ($payments->isNotEmpty()) {
                // Preload related orders/models to reduce queries
                // Collect unique order_ids and model_ids
                $orderIds = $payments->pluck('order_id')->unique()->filter()->values()->all();
                $modelIds = $payments->pluck('model_id')->unique()->filter()->values()->all();

                // Load orders (we need order->price)
                $orders = Order::whereIn('id', $orderIds)->get()->keyBy('id');

                // Load order_models and models minutes if needed (map order+model)
                // If your app has OrderModel model linking order->model with quantity etc, adapt accordingly.
                $orderModels = OrderModel::whereIn('order_id', $orderIds)
                    ->whereIn('model_id', $modelIds)
                    ->with('model:id,minute') // ensure model relation has minute field
                    ->get();

                // Build map for quick lookup: [order_id][model_id] => modelMinute (fallback 0)
                $orderModelMinuteMap = [];
                foreach ($orderModels as $om) {
                    $minutes = $om->model?->minute ?? 0;
                    $orderModelMinuteMap[$om->order_id][$om->model_id] = $minutes;
                }

                // We'll batch collect sewing outputs sums per (order_id, model_id, date)
                // Build keys to query: array of ['order_id'=>..., 'model_id'=>..., 'date'=>...]
                $keys = [];
                foreach ($payments as $p) {
                    // Normalize date to Y-m-d (payment_date may be datetime or date)
                    $date = Carbon::parse($p->payment_date)->toDateString();
                    $keys[$p->order_id . '|' . $p->model_id . '|' . $date] = [
                        'order_id' => $p->order_id,
                        'model_id' => $p->model_id,
                        'date' => $date
                    ];
                }
                $keys = array_values($keys);

                // Query sewing_outputs sums grouped by order_model/model and date
                // sewing_outputs -> order_sub_models -> order_models
                // We need to join order_sub_models and order_models and filter by order_id and model_id and date(created_at) = date
                $soQuery = SewingOutputs::join('order_sub_models', 'order_sub_models.id', '=', 'sewing_outputs.order_submodel_id')
                    ->join('order_models', 'order_models.id', '=', 'order_sub_models.order_model_id')
                    ->select(
                        'order_models.order_id',
                        'order_models.model_id',
                        DB::raw("DATE(sewing_outputs.created_at) as date"),
                        DB::raw('SUM(sewing_outputs.quantity) as qty')
                    )
                    ->groupBy('order_models.order_id', 'order_models.model_id', DB::raw("DATE(sewing_outputs.created_at)"));

                // Build where clauses to cover all keys (use where combinations)
                // To keep it efficient, filter by order_ids and model_ids
                if (!empty($orderIds)) {
                    $soQuery->whereIn('order_models.order_id', $orderIds);
                }
                if (!empty($modelIds)) {
                    $soQuery->whereIn('order_models.model_id', $modelIds);
                }

                // Execute and map results into lookup
                $soRows = $soQuery->get();
                // lookup: map[order_id][model_id][date] => qty
                $soMap = [];
                foreach ($soRows as $r) {
                    $soMap[$r->order_id][$r->model_id][$r->date] = (float)$r->qty;
                }

                // Now iterate payments and update calculated_amount
                $now = Carbon::now()->toDateTimeString();
                foreach ($payments as $p) {
                    $orderId = $p->order_id;
                    $modelId = $p->model_id;
                    $paymentDate = Carbon::parse($p->payment_date)->toDateString(); // Y-m-d
                    $employeePercent = (float)$p->employee_percentage; // e.g. 10 = 10%

                    // produced quantity for that day
                    $producedQty = $soMap[$orderId][$modelId][$paymentDate] ?? 0;

                    // get order
                    $order = $orders->get($orderId);

                    // determine unitCost based on departmentBudget type
                    $unitCost = 0.0;

                    if ($departmentBudget->type === 'minute_based') {
                        // get model minutes from map (orderModelMinuteMap)
                        $minutes = $orderModelMinuteMap[$orderId][$modelId] ?? 0;
                        // departmentBudget->quantity is minute price
                        $unitCost = (float)$departmentBudget->quantity * $minutes;
                    } elseif ($departmentBudget->type === 'percentage_based') {
                        $priceUzs = ($order->price ?? 0) * $usdRate;
                        $percent = ((float)$departmentBudget->quantity) / 100.0;
                        $unitCost = $priceUzs * $percent;
                    }

                    // newTotal for that day for this department (before employee share)
                    $newTotal = $producedQty * $unitCost;

                    // employee share
                    $employeeShare = 0.0;
                    if ($newTotal > 0 && $employeePercent > 0) {
                        $employeeShare = $newTotal * ($employeePercent / 100.0);
                    }

                    // round to 2 decimals (as your DB expects)
                    $newCalculated = round($employeeShare, 2);

                    // Update the daily_payment record (only calculated_amount)
                    DailyPayment::where('id', $p->id)
                        ->update([
                            'calculated_amount' => $newCalculated,
                            'updated_at' => $now
                        ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Department budget updated and related payments recalculated successfully.',
                'department_budget' => $departmentBudget,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            // rethrow or return error
            return response()->json([
                'message' => 'Failed to update department budget and recalculate payments.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updatePercentage(Request $request, Employee $employee): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()->employee->branch_id;

        // ✅ Branch security
        if ($employee->branch_id !== $branchId) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        // ✅ Validate
        $validated = $request->validate([
            'percentage' => 'required|numeric|min:0|max:100',
        ]);

        $newPercentage = (float)$validated['percentage'];

        DB::beginTransaction();
        try {
            // ✅ Update employee's main percentage
            $employee->update(['percentage' => $newPercentage]);

            // ✅ Get all daily payments of this employee
            $payments = DailyPayment::select('id', 'employee_percentage', 'calculated_amount')
                ->where('employee_id', $employee->id)
                ->get();

            if ($payments->isNotEmpty()) {
                $now = now();

                foreach ($payments as $p) {
                    $oldPercent = (float)$p->employee_percentage;
                    $oldAmount = (float)$p->calculated_amount;

                    // Skip if old percentage is 0 (avoid division by zero)
                    if ($oldPercent <= 0) {
                        continue;
                    }

                    // 1% qiymatini topamiz
                    $onePercentValue = $oldAmount / $oldPercent;

                    // Yangi qiymatni hisoblaymiz
                    $newCalculated = round($onePercentValue * $newPercentage, 2);

                    // Yangilaymiz
                    DailyPayment::where('id', $p->id)->update([
                        'employee_percentage' => $newPercentage,
                        'calculated_amount' => $newCalculated,
                        'updated_at' => $now,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Employee percentage updated and all related payments recalculated successfully.',
                'employee' => $employee,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update employee percentage and recalculate payments.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeExpense(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0',
            'type' => 'required|string|in:minute_based,percentage_based,fixed',
        ]);

        $branchId = auth()->user()->employee->branch_id;

        $expense = Expense::create([
            'name' => $validated['name'],
            'quantity' => $validated['quantity'],
            'type' => $validated['type'],
            'branch_id' => $branchId,
        ]);

        return response()->json([
            'message' => 'Expense saved successfully.',
            'expense' => $expense,
        ], 201);
    }

    public function updateExpense(Request $request, Expense $expense): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()->employee->branch_id;

        // Unauthorized access check
        if ($expense->branch_id !== $branchId) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|string|in:minute_based,percentage_based,fixed',
        ]);

        if (empty($validated)) {
            return response()->json(['message' => 'No fields provided to update.'], 422);
        }

        $expense->update($validated);

        return response()->json([
            'message' => 'Expense updated successfully.',
            'expense' => $expense,
        ]);
    }

    public function showExpense(Expense $expense): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()->employee->branch_id;

        if ($expense->branch_id !== $branchId) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        return response()->json([
            'id' => $expense->id,
            'name' => $expense->name,
            'quantity' => $expense->quantity,
            'type' => $expense->type,
            'created_at' => $expense->created_at,
        ]);
    }

    public function showDailyPaymentWithDay(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = $request->date ?? now()->toDateString();
        $branchId = auth()->user()->employee->branch_id ?? null;
        $departmentId = $request->department_id ?? null;

        $attendance = Attendance::where('date', $date)
            ->whereHas('employee', function ($q) use ($branchId, $departmentId) {
                $q->where('branch_id', $branchId);
                if ($departmentId) {
                    $q->where('department_id', $departmentId);
                }
            })
            ->get()
            ->map(function ($att) {
                return [
                    'id' => $att->id,
                    'employee_id' => $att->employee_id,
                    'status' => $att->status,
                    'check_in' => $att->check_in,
                    'check_out' => $att->check_out,
                    'check_in_image' => $att->check_in_image,
                    'check_out_image' => $att->check_out_image,
                ];
            })
            ->values();

        $payments = DailyPayment::with([
            'employee:id,name,img',
            'order:id,name',
            'order.orderModel:id,model_id,order_id',
            'order.orderModel.submodels:id,order_model_id,submodel_id',
            'order.orderModel.submodels.sewingOutputs' => function ($q) use ($date) {
                $q->whereDate('created_at', $date);
            }
        ])
            ->whereDate('payment_date', $date)
            ->whereHas('employee', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->when($departmentId, function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            })
            ->get()
            ->groupBy('employee_id')
            ->map(function ($group) {
                $employee = $group->first()->employee;

                $totalPotential = 0;

                $details = $group->map(function ($payment) use (&$totalPotential) {

                    // Shu order bo‘yicha shu kunda chiqgan quantity
                    $dayOutputQuantity = $payment->order->orderModel->submodels->sum(function ($submodel) {
                        return $submodel->sewingOutputs->sum('quantity');
                    });

                    // Agar mavjud bo‘lsa potensial daromadni hisoblash
                    $potential = $payment->quantity_produced > 0
                        ? (($payment->calculated_amount) / $payment->quantity_produced) * $dayOutputQuantity
                        : 0;

                    $potential = round($potential, 2);

                    $totalPotential += $potential;

                    return [
                        'payment_id' => $payment->id,
                        'order' => [
                            'id' => $payment->order->id,
                            'name' => $payment->order->name,
                        ],
                        'model' => $payment->order->orderModel?->model,
                        'submodel' => $payment->order->orderModel?->submodels->first()?->submodel,
                        'daily_quantity' => $payment->quantity_produced,
                        'day_output_quantity' => $dayOutputQuantity,
                        'calculated_amount' => round($payment->calculated_amount, 2),
                        'potential_earn' => $potential,
                        'employee_percentage' => round($payment->employee_percentage, 2),
                        'payment_date' => $payment->payment_date,
                        'bonus' => round($payment->bonus, 2)
                    ];
                })->values();

                return [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'img' => $employee->img,
                    'total_calculated_amount' => round($group->sum('calculated_amount'), 2) + round($group->sum('bonus'), 2),
                    'total_potential_earn' => round($totalPotential, 2),
                    'details' => $details,
                ];
            })->values();

        $workingEmployees = Employee::where('branch_id', $branchId)
            ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
            ->where('status', 'working') // status filter
            ->select('id', 'name', 'position_id', 'img')
            ->with('position:id,name')
            ->get();

        return response()->json([
            'payments' => $payments,
            'working_employees' => $workingEmployees,
            'attendances' => $attendance,
        ]);
    }

    public function editDailyPayment(Request $request, DailyPayment $dailyPayment): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'bonus' => 'required|numeric|min:0',
        ]);

        $dailyPayment->update([
            'bonus' => $validated['bonus']
        ]);

        return response()->json([
            'message' => 'DailyPayment updated successfully',
            'updated' => $dailyPayment
        ]);
    }

    public function storeDailyPayment(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'payment_date' => 'required|date',
        ]);

        $branchId = auth()->user()->employee->branch_id;

        $employee = Employee::where('id', $validated['employee_id'])
            ->where('branch_id', $branchId)
            ->whereHas('attendances', function ($q) use ($validated) {
                $q->whereDate('date', $validated['payment_date'])
                    ->where('status', 'present');
            })
            ->first();

        if (!$employee) {
            return response()->json([
                'message' => 'Belgilangan kunda hodim ishga kelmagan!'
            ], 404);
        }

        $orders = Order::where('branch_id', $branchId)
            ->whereHas('monthlySelectedOrder', function ($q) use ($validated) {
                $q->whereMonth('month', Carbon::parse($validated['payment_date'])->format('m'))
                  ->whereYear('month', Carbon::parse($validated['payment_date'])->format('Y'));
            })
            ->whereHas('orderModel.submodels.sewingOutputs', function ($q) use ($validated) {
                $q->whereDate('sewing_outputs.created_at', $validated['payment_date']);
            })
            ->with(['orderModel.model'])
            ->get();

        $departmentBudget = DepartmentBudget::where('department_id', $employee->department_id)->first();
        if (!$departmentBudget) {
            return response()->json([
                'message' => 'Hodimning bo‘limi uchun byudjet belgilanmagan!'
            ], 404);
        }

        foreach ($orders as $order) {
            $calculatedAmount = $order->orderModel->model->minute * $departmentBudget->quantity / 100 * $employee->percentage * 1;
            DailyPayment::firstOrCreate([
                'employee_id' => $employee->id,
                'order_id' => $order->id,
                'payment_date' => $validated['payment_date'],
            ], [
                'model_id' => $order->orderModel->model_id ?? null,
                'department_id' => $employee->department_id,
                'quantity_produced' => 1,
                'calculated_amount' => $calculatedAmount,
                'employee_percentage' => $employee->percentage,
                'bonus' => 0,
            ]);
        }

        return response()->json([
            'message' => 'DailyPayment created successfully',
        ], 201);
    }

    public function getCombinedCostAnalysis(Request $request): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()->employee->branch_id ?? null;
        $usdRate = $request->dollar_rate ?? getUsdRate();

        $selectedMonth = $request->month ?? now()->format('Y-m');
        $selectedSeasonYear = $request->season_year;
        $selectedSeasonType = $request->season_type;

        // Oyning boshi va oxiri
        $start = Carbon::parse($selectedMonth)->startOfMonth();
        $end = Carbon::parse($selectedMonth)->endOfMonth();

        // ====== REAL HARAJATLAR (Bo'limlar bo'yicha) ======

        // 1) Barcha bo'limlar uchun real harajatlar (DailyPayment dan)
        $departmentRealCosts = DailyPayment::select(
            'department_id',
            DB::raw('SUM(calculated_amount + bonus) as total_cost')
        )
            ->with('department:id,name')
            ->whereHas('order', function($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->whereNotNull('department_id')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('department_id')
            ->get()
            ->keyBy('department_id');

        // 2) Ishchilar uchun real harajatlar (department_id bo'lmaganlar)
        $workerRealTotal = DailyPayment::whereHas('order', function($q) use ($branchId) {
            $q->where('branch_id', $branchId);
        })
            ->whereNull('department_id')
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('calculated_amount + bonus'));

        // 3) Transport real harajati
        $transportRealTotal = DB::table('transport_attendance')
            ->join('transport', 'transport_attendance.transport_id', '=', 'transport.id')
            ->whereBetween('transport_attendance.date', [$start, $end])
            ->where('transport.branch_id', $branchId)
            ->sum(DB::raw('(transport.salary + transport.fuel_bonus) * transport_attendance.attendance_type'));

        // 4) Monthly expenses (oylik harajatlar)
        $monthlyExpensesReal = DB::table('monthly_expenses')
            ->where('branch_id', $branchId)
            ->whereMonth('month', $start->month)
            ->whereYear('month', $start->year)
            ->get();

        $monthlyTypeTotal = $monthlyExpensesReal->where('type', 'monthly')->sum('amount');
        $incomePercentageTotal = 0;
        $amortizationTotal = 0;

        // ====== BUYURTMALAR VA MAHSULOTLAR ======

        $orders = Order::query()
            ->with([
                'orderModel.model',
                'orderModel.submodels.submodel',
                'orderModel.submodels.group.group.responsibleUser',
                'monthlySelectedOrder',
                'dailyPayments' => function ($q) use ($branchId) {
                    $q->whereHas('employee', fn($q2) => $q2->where('branch_id', $branchId));
                }
            ])
            ->whereHas('monthlySelectedOrder', function ($q) use ($selectedMonth) {
                $q->whereMonth('month', date('m', strtotime($selectedMonth)))
                    ->whereYear('month', date('Y', strtotime($selectedMonth)));
            })
            ->where('branch_id', $branchId)
            ->when($selectedSeasonYear, fn($q) => $q->where('season_year', $selectedSeasonYear))
            ->when($selectedSeasonType, fn($q) => $q->where('season_type', $selectedSeasonType))
            ->get();

        // Jami ishlab chiqarilgan miqdor va daqiqalar
        $totalProducedQty = 0;
        $totalMinutes = 0;

        $orderResults = [];

        foreach ($orders as $order) {
            if (!$order->orderModel) continue;

            $orderModel = $order->orderModel;
            $model = $orderModel->model;

            // Ishlab chiqarilgan miqdor
            $producedQty = SewingOutputs::join('order_sub_models', 'order_sub_models.id', '=', 'sewing_outputs.order_submodel_id')
                ->join('order_models', 'order_models.id', '=', 'order_sub_models.order_model_id')
                ->where('order_models.order_id', $order->id)
                ->where('order_models.model_id', $model->id)
                ->whereBetween('sewing_outputs.created_at', [$start, $end])
                ->sum('sewing_outputs.quantity');

            $minutes = $producedQty * ($model->minute ?? 0);
            $totalProducedQty += $producedQty;
            $totalMinutes += $minutes;

            // Income percentage expense (bu buyurtma uchun)
            $priceUzs = ($order->price ?? 0) * $usdRate;
            $orderIncomePercentage = 0;
            foreach ($monthlyExpensesReal->where('type', 'income_percentage') as $exp) {
                $orderIncomePercentage += ($priceUzs * $producedQty) * ($exp->amount / 100);
            }
            $incomePercentageTotal += $orderIncomePercentage;

            // Amortization expense
            $orderAmortization = 0;
            if ($monthlyExpensesReal->where('type', 'amortization')->count() > 0) {
                $orderAmortization = $producedQty * 0.10 * $usdRate;
            }
            $amortizationTotal += $orderAmortization;

            // ====== PLANNED COSTS (Bo'limlar bo'yicha) ======

            $plannedDepartmentCosts = DepartmentBudget::with('department:id,name')
                ->whereHas('department.mainDepartment', fn($q) => $q->where('branch_id', $branchId))
                ->get()
                ->map(function ($db) use ($order, $model, $usdRate, $minutes, $producedQty) {
                    if ($db->type === 'minute_based') {
                        $plannedForOrder = $db->quantity * $minutes;
                    } elseif ($db->type === 'percentage_based') {
                        $priceUzs = ($order->price ?? 0) * $usdRate;
                        $plannedForOrder = $priceUzs * ($db->quantity / 100) * $producedQty;
                    } else {
                        $plannedForOrder = 0;
                    }

                    return [
                        'id' => $db->department_id,
                        'name' => $db->department?->name,
                        'type' => $db->type,
                        'rate' => $db->quantity,
                        'planned_total' => round($plannedForOrder, 2),
                        'planned_per_minute' => $minutes > 0 ? round($plannedForOrder / $minutes, 2) : 0,
                        'planned_per_unit' => $producedQty > 0 ? round($plannedForOrder / $producedQty, 2) : 0,
                    ];
                });

            // Bu buyurtma uchun real department costs
            $orderDepartmentRealCosts = DailyPayment::select(
                'department_id',
                DB::raw('SUM(calculated_amount + bonus) as cost')
            )
                ->with('department:id,name')
                ->where('order_id', $order->id)
                ->where('model_id', $model->id)
                ->whereNotNull('department_id')
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('department_id')
                ->get()
                ->keyBy('department_id');

            // Ishchilar uchun real (bu buyurtma)
            $orderWorkerReal = DailyPayment::where('order_id', $order->id)
                ->where('model_id', $model->id)
                ->whereNull('department_id')
                ->whereBetween('created_at', [$start, $end])
                ->sum(DB::raw('calculated_amount + bonus'));

            // Real va Planned ni birlashtirish
            $departmentComparison = $plannedDepartmentCosts->map(function ($planned) use ($orderDepartmentRealCosts, $minutes, $producedQty) {
                $realData = $orderDepartmentRealCosts->get($planned['id']);
                $realTotal = $realData ? $realData->cost : 0;

                return [
                    'id' => $planned['id'],
                    'name' => $planned['name'],
                    'type' => $planned['type'],
                    'planned_rate' => $planned['rate'],
                    'planned_total' => $planned['planned_total'],
                    'planned_per_minute' => $planned['planned_per_minute'],
                    'planned_per_unit' => $planned['planned_per_unit'],
                    'real_total' => round($realTotal, 2),
                    'real_per_minute' => $minutes > 0 ? round($realTotal / $minutes, 2) : 0,
                    'real_per_unit' => $producedQty > 0 ? round($realTotal / $producedQty, 2) : 0,
                    'difference' => round($realTotal - $planned['planned_total'], 2),
                    'difference_percent' => $planned['planned_total'] > 0
                        ? round((($realTotal - $planned['planned_total']) / $planned['planned_total']) * 100, 2)
                        : null,
                ];
            });

            // Expense harajatlar (planned)
            $expensesPlanned = Expense::where('branch_id', $branchId)->get()->map(function ($exp) use ($order, $model, $usdRate, $producedQty, $minutes) {
                $priceUzs = ($order->price ?? 0) * $usdRate;

                if ($exp->type === 'minute_based') {
                    $planned = $exp->quantity * $minutes;
                } elseif ($exp->type === 'percentage_based') {
                    $planned = $priceUzs * ($exp->quantity / 100) * $producedQty;
                } else {
                    $planned = 0;
                }

                return [
                    'id' => $exp->id,
                    'name' => $exp->name,
                    'type' => $exp->type,
                    'rate' => $exp->quantity,
                    'planned_total' => round($planned, 2),
                    'planned_per_minute' => $minutes > 0 ? round($planned / $minutes, 2) : 0,
                    'planned_per_unit' => $producedQty > 0 ? round($planned / $producedQty, 2) : 0,
                ];
            });

            $orderResults[] = [
                'order' => $order,
                'model' => [
                    'id' => $model->id,
                    'name' => $model->name,
                    'minute' => $model->minute,
                ],
                'produced_quantity' => $producedQty,
                'minutes' => $minutes,
                'price_uzs' => $priceUzs,
                'workers_real_cost' => round($orderWorkerReal, 2),
                'departments' => $departmentComparison,
                'expenses_planned' => $expensesPlanned,
                'income_percentage_expense' => round($orderIncomePercentage, 2),
                'amortization_expense' => round($orderAmortization, 2),
            ];
        }

        // ====== UMUMIY REAL HARAJATLARNI BO'LIMLAR BO'YICHA TESKARI HISOBLASH ======

        // Bo'limlar bo'yicha real harajatlar breakdown
        $departmentRealBreakdown = $departmentRealCosts->map(function($dept) use ($totalMinutes, $totalProducedQty) {
            return [
                'id' => $dept->department_id,
                'name' => $dept->department?->name ?? 'Noma\'lum',
                'real_total' => round($dept->total_cost, 2),
                'real_per_minute' => $totalMinutes > 0 ? round($dept->total_cost / $totalMinutes, 2) : 0,
                'real_per_unit' => $totalProducedQty > 0 ? round($dept->total_cost / $totalProducedQty, 2) : 0,
            ];
        })->values();

        // Boshqa umumiy harajatlar
        $otherRealCosts = [
            'workers' => [
                'name' => 'Tikuvchilar (Ishchilar)',
                'real_total' => round($workerRealTotal, 2),
                'real_per_minute' => $totalMinutes > 0 ? round($workerRealTotal / $totalMinutes, 2) : 0,
                'real_per_unit' => $totalProducedQty > 0 ? round($workerRealTotal / $totalProducedQty, 2) : 0,
            ],
            'transport' => [
                'name' => 'Transport',
                'real_total' => round($transportRealTotal, 2),
                'real_per_minute' => $totalMinutes > 0 ? round($transportRealTotal / $totalMinutes, 2) : 0,
                'real_per_unit' => $totalProducedQty > 0 ? round($transportRealTotal / $totalProducedQty, 2) : 0,
            ],
            'monthly_expenses' => [
                'name' => 'Oylik harajatlar',
                'real_total' => round($monthlyTypeTotal, 2),
                'real_per_minute' => $totalMinutes > 0 ? round($monthlyTypeTotal / $totalMinutes, 2) : 0,
                'real_per_unit' => $totalProducedQty > 0 ? round($monthlyTypeTotal / $totalProducedQty, 2) : 0,
            ],
            'income_percentage' => [
                'name' => 'Foizli harajatlar',
                'real_total' => round($incomePercentageTotal, 2),
                'real_per_minute' => $totalMinutes > 0 ? round($incomePercentageTotal / $totalMinutes, 2) : 0,
                'real_per_unit' => $totalProducedQty > 0 ? round($incomePercentageTotal / $totalProducedQty, 2) : 0,
            ],
            'amortization' => [
                'name' => 'Amortizatsiya',
                'real_total' => round($amortizationTotal, 2),
                'real_per_minute' => $totalMinutes > 0 ? round($amortizationTotal / $totalMinutes, 2) : 0,
                'real_per_unit' => $totalProducedQty > 0 ? round($amortizationTotal / $totalProducedQty, 2) : 0,
            ],
        ];

        $totalDepartmentRealCost = $departmentRealCosts->sum('total_cost');
        $totalRealCost = $totalDepartmentRealCost + $workerRealTotal + $transportRealTotal +
            $monthlyTypeTotal + $incomePercentageTotal + $amortizationTotal;

        return response()->json([
            'period' => [
                'month' => $selectedMonth,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'usd_rate' => $usdRate,
            'production_summary' => [
                'total_produced_quantity' => $totalProducedQty,
                'total_minutes' => $totalMinutes,
            ],
            'real_costs_breakdown' => [
                'departments' => $departmentRealBreakdown,
                'others' => $otherRealCosts,
            ],
            'total_real_cost' => round($totalRealCost, 2),
            'total_real_cost_per_minute' => $totalMinutes > 0 ? round($totalRealCost / $totalMinutes, 2) : 0,
            'total_real_cost_per_unit' => $totalProducedQty > 0 ? round($totalRealCost / $totalProducedQty, 2) : 0,
            'orders' => $orderResults,
        ]);
    }
}
