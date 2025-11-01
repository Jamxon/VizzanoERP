<?php

namespace App\Http\Controllers;

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

        /* ✅ Worker expenses (Master & Texnolog bo‘lmagan ishchilar) */
        $workers = DailyPayment::select(
            'daily_payments.department_id',
            DB::raw('SUM(daily_payments.calculated_amount) as total_amount')
        )
            ->join('employees', 'employees.id', 'daily_payments.employee_id')
            ->join('positions', 'positions.id', 'employees.position_id')
            ->when($start, fn($q) => $q->where('payment_date', '>=', $start))
            ->when($end, fn($q) => $q->where('payment_date', '<=', $end))
            ->where('employees.branch_id', $branchId)
            ->whereNotIn('positions.name', ['Master', 'Texnolog'])
            ->groupBy('daily_payments.department_id')
            ->with('department:id,name')
            ->get();

        $totalWorkerCost = $workers->sum('total_amount');

        /* ✅ Master & Texnolog alohida */
        $special = DailyPayment::select(
            DB::raw("positions.name as position"),
            DB::raw('SUM(calculated_amount) as total_amount')
        )
            ->join('employees', 'employees.id', 'daily_payments.employee_id')
            ->join('positions', 'positions.id', 'employees.position_id')
            ->when($start, fn($q) => $q->where('payment_date', '>=', $start))
            ->when($end, fn($q) => $q->where('payment_date', '<=', $end))
            ->whereIn('positions.name', ['Master', 'Texnolog'])
            ->where('employees.branch_id', $branchId)
            ->groupBy('position')
            ->get();

        /* ✅ Model kesimi */
        $modelCosts = DailyPayment::select(
            'model_id',
            DB::raw('SUM(calculated_amount) as total_amount')
        )
            ->when($start, fn($q) => $q->where('payment_date', '>=', $start))
            ->when($end, fn($q) => $q->where('payment_date', '<=', $end))
            ->whereHas('employee', fn($q) => $q->where('branch_id', $branchId))
            ->groupBy('model_id')
            ->with('model:id,name')
            ->get();

        /* ✅ Order kesimi */
        $orderCosts = DailyPayment::select(
            'order_id',
            DB::raw('SUM(calculated_amount) as total_amount')
        )
            ->when($start, fn($q) => $q->where('payment_date', '>=', $start))
            ->when($end, fn($q) => $q->where('payment_date', '<=', $end))
            ->whereHas('employee', fn($q) => $q->where('branch_id', $branchId))
            ->groupBy('order_id')
            ->with('order')
            ->get();

        /* ✅ SewingOutputs orqali umumiy daqiqalar — TO‘G‘RI HISOB */
        $sewingMinutesTotal = SewingOutputs::select(
            DB::raw('SUM(sewing_outputs.quantity * models.minute) as total_minutes')
        )
            ->join('order_sub_models', 'order_sub_models.id', '=', 'sewing_outputs.order_submodel_id')
            ->join('order_models', 'order_models.id', '=', 'order_sub_models.order_model_id')
            ->join('orders', 'orders.id', '=', 'order_models.order_id')
            ->join('models', 'models.id', '=', 'order_models.model_id')
            ->when($start, fn($q) => $q->where('sewing_outputs.created_at', '>=', $start))
            ->when($end, fn($q) => $q->where('sewing_outputs.created_at', '<=', $end))
            ->where('orders.branch_id', $branchId)
            ->value('total_minutes') ?? 0;

        /* ✅ Expenses hisoblash (type bo‘yicha) */
//        $expenses = Expense::where('branch_id', $branchId)->get()->map(function ($exp) use ($totalMinutes, $totalWorkerCost) {
            $expenses = DB::table('expenses')->where('branch_id', $branchId)->get()->map(function ($exp) use ($sewingMinutesTotal, $totalWorkerCost) {
            if ($exp->type === 'minute_based') {
                $exp->total_amount = $sewingMinutesTotal * $exp->quantity;
            } elseif ($exp->type === 'percent_based') {
                $exp->total_amount = ($sewingMinutesTotal / 100) * $exp->quantity;
            } elseif ($exp->type === 'fixed') {
                $exp->total_amount = $exp->quantity;
            } else {
                $exp->total_amount = 0;
            }

            return $exp;
        });

        return response()->json([
            'success' => true,
            'department_costs' => $workers,
            'special_positions' => $special,
            'model_costs' => $modelCosts,
            'order_costs' => $orderCosts,
            'expenses' => $expenses,
            'totals' => [
                'worker_total' => $totalWorkerCost,
                'expense_total' => $expenses->sum('total_amount'),
                'overall_total_cost' => $totalWorkerCost + $expenses->sum('total_amount')
            ]
        ]);
    }
}
