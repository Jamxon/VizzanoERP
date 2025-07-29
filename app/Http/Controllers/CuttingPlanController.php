<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;


class CuttingPlanController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = \App\Models\CuttingPlan::whereHas('department.mainDepartment', function ($q) {
            $q->where('branch_id', auth()->user()->employee->branch_id);
        });

        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $query->where('month', $month)->where('year', $year)
            ->with(['department.responsibleUser.employee']);

        $plans = $query->get();

        $branchId = auth()->user()->employee->branch_id;

        $plans->map(function ($plan) use ($month, $year, $branchId) {

            // Butun oy bo'yicha quantity summasi
            $monthlyDone = \App\Models\OrderCut::whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->whereHas('order', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                })
                ->sum('quantity');

            $plan->monthly_total = $monthlyDone;

            // Har kunlik natijalar
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $days = [];
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dailyDone = \App\Models\OrderCut::whereDate('created_at', $date)
                    ->whereHas('order', function ($q) use ($branchId) {
                        $q->where('branch_id', $branchId);
                    })
                    ->sum('quantity');

                $days[] = [
                    'date' => $date->toDateString(),
                    'quantity' => $dailyDone,
                ];
            }

            $plan->days = $days;

            return $plan;
        });

        return response()->json($plans);
    }

    public function store(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'quantity' => 'required|integer|min:1',
        ]);

        $plan = \App\Models\CuttingPlan::create($data);

        return response()->json($plan, 201);
    }

    public function show($id): \Illuminate\Http\JsonResponse
    {
        $plan = \App\Models\CuttingPlan::with('department.responsibleUser.employee')->findOrFail($id);

        return response()->json($plan);
    }

    public function update(\Illuminate\Http\Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'department_id' => 'sometimes|exists:departments,id',
            'month' => 'sometimes|integer|min:1|max:12',
            'year' => 'sometimes|integer|min:2000|max:2100',
            'quantity' => 'sometimes|integer|min:1',
        ]);

        $plan = \App\Models\CuttingPlan::findOrFail($id);
        $plan->update($data);

        return response()->json($plan);
    }

    public function destroy($id): \Illuminate\Http\JsonResponse
    {
        $plan = \App\Models\CuttingPlan::findOrFail($id);
        $plan->delete();

        return response()->json(null, 204);
    }
}