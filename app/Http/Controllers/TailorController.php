<?php

namespace App\Http\Controllers;

use App\Models\EmployeeTarificationLog;
use App\Models\Tarification;
use Illuminate\Http\Request;

class TailorController extends Controller
{
    public function searchTarifications(Request $request): \Illuminate\Http\JsonResponse
    {
        $code = $request->input('code');

        $tarifications = Tarification::where('code', $code)
            ->with([
                'employee',
                'razryad',
                'typewriter',
                'tarificationLogs' => function ($query) {
                    $query->whereDate('date', now()->format('Y-m-d'));
                }
            ])
            ->orderBy('id', 'desc')
            ->first();

        return response()->json($tarifications);
    }

    public function getDailyBalanceEmployee(): \Illuminate\Http\JsonResponse
    {
        $today = now()->format('Y-m-d');

        $employeeTarificationLogs = EmployeeTarificationLog::where('date', $today)
            ->where('employee_id', auth()->user()->employee->id)
            ->sum('amount_earned')
            ?? 0;

        return response()->json($employeeTarificationLogs);
    }
}