<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\SewingOutputs;
use Illuminate\Http\RedirectResponse;
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

        $selectedSeasonYear = $request->season_year ?? 2026;
        $selectedSeasonType = $request->season_type ?? 'summer';

        $modelData = DailyPayment::select(
            'model_id',
            'order_id',
            DB::raw('SUM(calculated_amount) as worker_cost')
        )
            ->with([
                'model:id,name,minute',
                'order:id,name,quantity,price,season_year,season_type'
            ])
            ->whereHas('order', function ($q) use ($selectedSeasonYear, $selectedSeasonType) {
                $q->where('season_year', $selectedSeasonYear)
                    ->where('season_type', $selectedSeasonType);
            })
            ->whereHas('employee', fn($q) => $q->where('branch_id', $branchId))
            ->groupBy('model_id', 'order_id')
            ->get()
            ->map(function ($row) use ($usdRate, $branchId) {

                /**
                 * ✅ Produced Quantity
                 */
                $produced = SewingOutputs::join('order_sub_models', 'order_sub_models.id', '=', 'sewing_outputs.order_submodel_id')
                    ->join('order_models', 'order_models.id', '=', 'order_sub_models.order_model_id')
                    ->where('order_models.order_id', $row->order_id)
                    ->where('order_models.model_id', $row->model_id)
                    ->sum('sewing_outputs.quantity');

                $minutes = $produced * ($row->model->minute ?? 0);

                /**
                 * ✅ Planned Calculation base
                 */
                $orderedQuantity = $row->order->quantity;
                $ratio = $produced > 0 ? ($orderedQuantity / $produced) : 0;

                /**
                 * ✅ Department Actual Costs
                 */
                $departmentCosts = DailyPayment::select(
                    'department_id',
                    DB::raw('SUM(calculated_amount) as cost')
                )
                    ->with('department:id,name')
                    ->where('order_id', $row->order_id)
                    ->where('model_id', $row->model_id)
                    ->whereNotNull('department_id')
                    ->groupBy('department_id')
                    ->get()
                    ->map(fn($d) => [
                        'department_id' => $d->department_id,
                        'department_name' => $d->department?->name,
                        'cost' => $d->cost,
                    ]);

                /**
                 * ✅ Planned Department Detailed Costs
                 */
                $plannedDepartmentCosts = collect($departmentCosts)->map(function ($dc) use ($ratio) {
                    return [
                        'department_id' => $dc['department_id'],
                        'department_name' => $dc['department_name'],
                        'planned_cost' => round($dc['cost'] * $ratio, 2)
                    ];
                });

                /**
                 * ✅ Employee-wise Details
                 */
                $employees = DailyPayment::select(
                    'employee_id',
                    'department_id',
                    DB::raw('SUM(quantity_produced) as quantity'),
                    DB::raw('SUM(calculated_amount) as salary'),
                    DB::raw('AVG(employee_percentage) as percentage')
                )
                    ->with([
                        'employee:id,name',
                        'department:id,name'
                    ])
                    ->where('order_id', $row->order_id)
                    ->where('model_id', $row->model_id)
                    ->groupBy('employee_id', 'department_id')
                    ->get()
                    ->map(fn($e) => [
                        'employee_id' => $e->employee_id,
                        'employee_name' => $e->employee?->name,
                        'department_id' => $e->department_id,
                        'department_name' => $e->department?->name,
                        'percentage' => round($e->percentage, 2),
                        'quantity_produced' => $e->quantity,
                        'salary' => round($e->salary, 2),
                    ]);

                /**
                 * ✅ Expenses Calculation
                 */
                $expenses = Expense::where('branch_id', $branchId)
                    ->get()
                    ->map(function ($exp) use ($row, $produced, $usdRate) {

                        if ($exp->type === 'minute_based') {
                            $cost = ($row->model->minute ?? 0) * $exp->quantity * $produced;
                        } elseif ($exp->type === 'percentage_based') {
                            $priceUzs = ($row->order->price ?? 0) * $usdRate;
                            $cost = $priceUzs * ($exp->quantity / 100) * $produced;
                        } else {
                            $cost = 0;
                        }

                        return [
                            'expense_id' => $exp->id,
                            'expense_name' => $exp->name,
                            'expense_type' => $exp->type,
                            'cost' => round($cost, 2),
                        ];
                    });

                /**
                 * ✅ Planned Expense Detailed Costs
                 */
                $plannedExpenses = collect($expenses)->map(function ($ex) use ($ratio) {
                    return [
                        'expense_id' => $ex['expense_id'],
                        'expense_name' => $ex['expense_name'],
                        'expense_type' => $ex['expense_type'],
                        'planned_cost' => round($ex['cost'] * $ratio, 2),
                    ];
                });

                $departmentTotal = collect($departmentCosts)->sum('cost');
                $expensesTotal = collect($expenses)->sum('cost');

                $plannedWorkerCost = round($row->worker_cost * $ratio, 2);
                $plannedDepartmentTotal = collect($plannedDepartmentCosts)->sum('planned_cost');
                $plannedExpensesTotal = collect($plannedExpenses)->sum('planned_cost');

                return [
                    'order' => [
                        'id' => $row->order->id,
                        'name' => $row->order->name,
                        'quantity' => $row->order->quantity,
                        'price' => $row->order->price,
                        'season_year' => $row->order->season_year,
                        'season_type' => $row->order->season_type,
                    ],
                    'model' => [
                        'id' => $row->model->id,
                        'name' => $row->model->name,
                        'minute' => $row->model->minute,
                    ],
                    'produced_quantity' => $produced,
                    'minutes' => $minutes,
                    'worker_cost' => round($row->worker_cost, 2),
                    'employee_details' => $employees,
                    'department_costs' => $departmentCosts,
                    'expenses_costs' => $expenses,
                    'total_cost' => round($row->worker_cost + $departmentTotal + $expensesTotal, 2),

                    /**
                     * ✅ FULL ORDER PLANNED COST SECTION WITH DETAILS ✅
                     */
                    'planned_costs' => [
                        'worker_planned_cost' => $plannedWorkerCost,

                        'department' => [
                            'total' => $plannedDepartmentTotal,
                            'details' => $plannedDepartmentCosts,
                        ],
                        'expenses' => [
                            'total' => $plannedExpensesTotal,
                            'details' => $plannedExpenses,
                        ],

                        'total_planned_cost' => round(
                            $plannedWorkerCost +
                            $plannedDepartmentTotal +
                            $plannedExpensesTotal,
                            2
                        ),
                    ]
                ];
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

        $selectedSeasonYear = $request->season_year ?? 2026;
        $selectedSeasonType = $request->season_type ?? 'summer';
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
            'employee_percentage'
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
            ->where('calculated_amount', '>', 0)
            ->whereHas('order', function ($q) use ($selectedSeasonYear, $selectedSeasonType) {
                $q->where('season_year', $selectedSeasonYear)
                    ->where('season_type', $selectedSeasonType);
            })
            ->when($orderId, fn($q) => $q->where('order_id', $orderId))
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
                    'calculated_amount' => $row->calculated_amount,
                    'employee_percentage' => round($row->employee_percentage, 2),
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

        // ✅ Branch security
        if ($departmentBudget->department->mainDepartment->branch_id !== $branchId) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        // ✅ Qaysi maydonlar yuborilgan bo‘lsa, faqat o‘sha tekshiriladi
        $validated = $request->validate([
            'quantity' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|string|in:minute_based,percentage_based',
        ]);

        // ✅ Faqat yuborilgan maydonlarni yangilash
        $departmentBudget->update($validated);

        return response()->json([
            'message' => 'Department budget updated successfully.',
            'department_budget' => $departmentBudget
        ]);
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
