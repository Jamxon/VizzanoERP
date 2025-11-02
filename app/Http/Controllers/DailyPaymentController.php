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
    public function index(Request $request)
    {
        $branchId = auth()->user()->employee->branch_id ?? null;
        $start = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : null;
        $end = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : null;

        $usdRate = getUsdRate(); // ✅ Bir marta olish kifoya

        $modelData = DailyPayment::select(
            'model_id',
            'order_id',
            DB::raw('SUM(calculated_amount) as worker_cost')
        )
            ->with([
                'model:id,name,minute',
                'order:id,name,quantity,price',
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
                 * ✅ Department Cost hisoblash
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
                 * ✅ Master / Texnolog / Expense hisoblash
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

                $expensesTotal = collect($expenses)->sum('cost');
                $departmentTotal = collect($departmentCosts)->sum('cost');

                return [
                    'order' => [
                        'id' => $row->order?->id,
                        'name' => $row->order?->name,
                        'quantity' => $row->order?->quantity,
                        'price' => $row->order?->price,
                    ],
                    'model' => [
                        'id' => $row->model?->id,
                        'name' => $row->model?->name,
                        'minute' => $row->model?->minute,
                    ],
                    'produced_quantity' => $produced,
                    'minutes' => $minutes,
                    'worker_cost' => $row->worker_cost,
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

        $departments = Department::whereHas('mainDepartment.branch_id', $branchId)
            ->with(['departmentBudget'])
            ->withCount('employees')
            ->with(['employees' => function ($q) use ($start, $end) {
                $q->select('id', 'name', 'phone', 'department_id', 'percentage', 'position_id')
                    ->withCount(['attendance as attendance_present_count' => function ($sub) use ($start, $end) {
                        $sub->where('status', 'present')
                            ->when($start, fn($q) => $q->where('date', '>=', $start))
                            ->when($end, fn($q) => $q->where('date', '<=', $end));
                    }]);

                $q->with('position:id,name');
            }])
            ->get()
            ->map(function ($department) {
                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'employee_count' => $department->employees_count,
                    'budget' => $department->departmentBudget,
                    'employees' => $department->employees->map(fn($e) => [
                        'id' => $e->id,
                        'name' => $e->name,
                        'phone' => $e->phone,
                        'position' => $e->position,
                        'percentage' => $e->percentage,
                        'attendance_present_count' => $e->attendance_present_count,
                    ]),
                ];
            });

        return response()->json($departments);
    }
}
