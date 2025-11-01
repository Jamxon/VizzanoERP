<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DailyPayment;
use Carbon\Carbon;

class DailyPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = DailyPayment::with([
            'employee:id,name',
            'employee.position:id,name',
            'model:id,name',
            'order:id,name',
            'department:id,name',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($request->has('start_date')) {
            $start = Carbon::parse($startDate)->startOfDay();
            $query->where('payment_date', '>=', $start);
        }

        if ($request->has('end_date')) {
            $end = Carbon::parse($endDate)->endOfDay();
            $query->where('payment_date', '<=', $end);
        }

        $branchId = auth()->user()->employee->branch_id ?? null;
        if ($branchId) {
            $query->whereHas('employee', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        $result = $query->orderBy('id', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
