<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Expense;
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
        $start = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : null;
        $end = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : null;

        $usdRate = getUsdRate();

        $modelData = DailyPayment::select(
            'model_id',
            'order_id',
            DB::raw('SUM(calculated_amount) as worker_cost')
        )
            ->with([
                'model:id,name,minute',
                'order:id,name,quantity,price'
            ])
            ->when($start, fn($q) => $q->where('payment_date', '>=', $start))
            ->when($end, fn($q) => $q->where('payment_date', '<=', $end))
            ->whereHas('employee', fn($q) => $q->where('branch_id', $branchId))
            ->groupBy('model_id', 'order_id')
            ->get()
            ->map(function ($row) use ($start, $end, $usdRate, $branchId) {

                /**
                 * ✅ Produced Quantity
                 */
                $produced = SewingOutputs::join('order_sub_models', 'order_sub_models.id', '=', 'sewing_outputs.order_submodel_id')
                    ->join('order_models', 'order_models.id', '=', 'order_sub_models.order_model_id')
                    ->where('order_models.order_id', $row->order_id)
                    ->where('order_models.model_id', $row->model_id)
                    ->when($start, fn($q) => $q->where('sewing_outputs.created_at', '>=', $start))
                    ->when($end, fn($q) => $q->where('sewing_outputs.created_at', '<=', $end))
                    ->sum('sewing_outputs.quantity');

                $minutes = $produced * ($row->model->minute ?? 0);

                /**
                 * ✅ Department costs
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
                 * ✅ Employees detail costs
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
                 * ✅ Expenses (Master/Texnolog etc)
                 */
                $expenses = Expense::where('branch_id', $branchId)
                    ->get()
                    ->map(function ($exp) use ($row, $produced, $usdRate) {

                        if ($exp->type === 'minute_based') {
                            $cost = ($row->model->minute ?? 0) * $exp->quantity * $produced;
                        } elseif ($exp->type === 'percent_based') {
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

                $departmentTotal = collect($departmentCosts)->sum('cost');
                $expensesTotal = collect($expenses)->sum('cost');

                return [
                    'order' => [
                        'id' => $row->order->id,
                        'name' => $row->order->name,
                        'quantity' => $row->order->quantity,
                        'price' => $row->order->price,
                    ],
                    'model' => [
                        'id' => $row->model->id,
                        'name' => $row->model->name,
                        'minute' => $row->model->minute,
                    ],
                    'produced_quantity' => $produced,
                    'minutes' => $minutes,
                    'worker_cost' => $row->worker_cost,
                    'employee_details' => $employees,
                    'department_costs' => $departmentCosts,
                    'expenses_costs' => $expenses,
                    'total_cost' => $row->worker_cost + $departmentTotal + $expensesTotal,
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
}
