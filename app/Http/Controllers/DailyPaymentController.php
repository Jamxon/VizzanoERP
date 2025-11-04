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

        // Branch-based authorization: faqat o'z filialidagi departmentlarga ruxsat
        if ($department->mainDepartment->branch_id !== $branchId) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        // Eager load: budget va employees (position bilan). Select bilan faqat keraklilarni olamiz.
        $department->load([
            'departmentBudget',
            'employees' => function ($q) {
                $q->where('status', 'working');
                $q->select('id', 'name', 'phone', 'department_id', 'percentage', 'position_id', 'img')
                    ->with('position:id,name');
            }
        ]);

        $employees = $department->employees->map(function ($e) {
            return [
                'id' => $e->id,
                'name' => $e->name,
                'phone' => $e->phone,
                'position' => $e->position?->name,
                'percentage' => $e->percentage,
                'img' => $e->img,
            ];
        });

        return response()->json([
            'id' => $department->id,
            'name' => $department->name,
            'budget' => $department->departmentBudget ? [
                'id' => $department->departmentBudget->id,
                'quantity' => $department->departmentBudget->quantity,
                'type' => $department->departmentBudget->type,
            ] : null,
            'employee_count' => $department->employees->count(),
            'employees' => $employees,
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

        // ✅ Only validate if sent
        $validated = $request->validate([
            'percentage' => 'sometimes|numeric|min:0|max:100',
        ]);

        if (!isset($validated['percentage'])) {
            return response()->json([
                'message' => 'No data provided to update.'
            ], 422);
        }

        // ✅ O‘zgartirish
        $employee->update([
            'percentage' => $validated['percentage'],
        ]);

        return response()->json([
            'message' => 'Employee percentage updated successfully.',
            'employee' => $employee
        ]);
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
