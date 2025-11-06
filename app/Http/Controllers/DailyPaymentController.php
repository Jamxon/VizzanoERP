<?php

namespace App\Http\Controllers;

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
            ->when($selectedSeasonYear, fn($q) =>
            $q->where('season_year', $selectedSeasonYear)
            )
            ->when($selectedSeasonType, fn($q) =>
            $q->where('season_type', $selectedSeasonType)
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
                        DB::raw('SUM(calculated_amount) as cost')
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
                            'minute' => $model->minute
                        ],
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
            ->map(function ($row)  {


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

    public function show(Department $department): \Illuminate\Http\JsonResponse
    {
        $branchId = auth()->user()->employee->branch_id ?? null;

        if ($department->mainDepartment->branch_id !== $branchId) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        $department->load([
            'departmentBudget',
            'employees' => function ($q) {
                $q->where('status', 'working')
                    ->select('id', 'name', 'phone', 'department_id', 'percentage', 'position_id', 'img', 'branch_id')
                    ->with('position:id,name');
            }
        ]);

        $usdRate = getUsdRate();
        $seasonYear = 2026;
        $seasonType = 'summer';

        $departmentTotal = [
            'total_earned' => 0,
            'total_remaining' => 0,
            'total_possible' => 0,
            'total_possible_season' => 0,
        ];

        $employeesData = $department->employees->map(function ($employee) use ($usdRate, $seasonYear, $seasonType, $departmentTotal) {
            $branchId = $employee->branch_id;
            $empPercent = floatval($employee->percentage ?? 0);

            // --- Monthly orders for this employee
            $month = now()->format('m');
            $year = now()->format('Y');

            $data = DB::table('orders')
                ->select(
                    'orders.id as order_id',
                    'orders.name as order_name',
                    'orders.quantity as planned_quantity',
                    'order_models.model_id',
                    'models.name as model_name',
                    'models.minute as model_minute',
                    DB::raw("COALESCE(SUM(daily_payments.calculated_amount),0) as earned_amount"),
                    DB::raw("COALESCE(SUM(daily_payments.quantity_produced),0) as produced_quantity"),
                    DB::raw("MAX(daily_payments.department_id) as department_id"),
                    'orders.price'
                )
                ->join('order_models', 'order_models.order_id', '=', 'orders.id')
                ->join('models', 'models.id', '=', 'order_models.model_id')
                ->leftJoin('daily_payments', function($q) use ($employee, $month, $year) {
                    $q->on('daily_payments.order_id', '=', 'orders.id')
                        ->where('daily_payments.employee_id', '=', $employee->id)
                        ->whereMonth('daily_payments.payment_date', $month)
                        ->whereYear('daily_payments.payment_date', $year);
                })
                ->leftJoin('monthly_selected_orders', function($q) use ($month, $year) {
                    $q->on('monthly_selected_orders.order_id', '=', 'orders.id')
                        ->whereMonth('monthly_selected_orders.month', $month)
                        ->whereYear('monthly_selected_orders.month', $year);
                })
                ->where('orders.branch_id', $branchId)
                ->whereExists(function($query) use ($month, $year) {
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
                    'departmentBudget' => $departmentBudget,
                    'department_id' => $row->department_id,
                    'empPercent' => $empPercent,
                    "earned_amount" => round($row->earned_amount, 2),
                    "remaining_earn_amount" => round($remainingQuantity * $perPieceEarn, 2),
                    "possible_full_earn_amount" => round($row->planned_quantity * $perPieceEarn, 2),
                ];
            });

            $monthlyTotal = [
                'total_earned' => round($orders->sum('earned_amount'), 2),
                'total_remaining' => round($orders->sum('remaining_earn_amount'), 2),
                'total_possible' => round($orders->sum('possible_full_earn_amount'), 2),
            ];

            // --- Season orders for this employee
            $seasonOrders = DB::table('orders')
                ->select('orders.id','orders.quantity','order_models.model_id','models.minute','orders.price')
                ->join('order_models','order_models.order_id','=','orders.id')
                ->join('models','models.id','=','order_models.model_id')
                ->where('orders.branch_id',$employee->branch_id)
                ->where('orders.season_year',$seasonYear)
                ->where('orders.season_type',$seasonType)
                ->get();

            $totalPossibleSeason = 0;
            foreach($seasonOrders as $row){
                $departmentBudget = DB::table('department_budgets')->where('department_id',$employee->department_id)->first();
                if(!$departmentBudget || $departmentBudget->quantity<=0) continue;

                $perPieceEarn = 0;
                if($departmentBudget->type==='minute_based'){
                    $perPieceEarn = $row->minute * $departmentBudget->quantity /100 * $empPercent;
                }elseif($departmentBudget->type==='percentage_based'){
                    $priceUzs = ($row->price ?? 0) * $usdRate;
                    $perPieceEarn = (($priceUzs * $departmentBudget->quantity)/100) * ($empPercent/100);
                }
                $totalPossibleSeason += $row->quantity * $perPieceEarn;
            }

            $monthlyTotal['total_possible_season'] = round($totalPossibleSeason,2);

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'percentage' => $employee->percentage,
                'orders' => $orders,
                'totals' => $monthlyTotal,
            ];
        });

        // --- Department totals
        $departmentTotals = [
            'total_earned' => round($employeesData->sum(fn($e) => $e['totals']['total_earned']),2),
            'total_remaining' => round($employeesData->sum(fn($e) => $e['totals']['total_remaining']),2),
            'total_possible' => round($employeesData->sum(fn($e) => $e['totals']['total_possible']),2),
            'total_possible_season' => round($employeesData->sum(fn($e) => $e['totals']['total_possible_season']),2),
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
    
        $newPercentage = (float) $validated['percentage'];
    
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
                    $oldPercent = (float) $p->employee_percentage;
                    $oldAmount  = (float) $p->calculated_amount;
    
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
                        'calculated_amount'   => $newCalculated,
                        'updated_at'          => $now,
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

}
