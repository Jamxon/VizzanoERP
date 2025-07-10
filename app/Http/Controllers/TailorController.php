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

    public function storeTarificationLog(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'tarification_id' => 'required|exists:tarifications,id',
        ]);

        $employeeId = auth()->user()->employee->id;
        $today = now()->toDateString();

        $tarification = Tarification::find($validated['tarification_id']);

        $isOwn = $tarification->employee_id === $employeeId;
        $amount = $tarification->summa;

        $logData = [
            'employee_id'     => $employeeId,
            'tarification_id' => $tarification->id,
            'date'            => $today,
            'quantity'        => 1,
            'is_own'          => $isOwn,
            'amount_earned'   => $amount,
        ];

        // logni saqlaymiz
        $log = EmployeeTarificationLog::updateOrCreate(
            [
                'employee_id'     => $employeeId,
                'tarification_id' => $tarification->id,
                'date'            => $today,
            ],
            $logData
        );

        // Faqat yangi log bo‘lsa, balansni oshiramiz
        if ($log->wasRecentlyCreated) {
            \App\Models\Employee::where('id', $employeeId)->increment('balance', $amount);
        }

        return response()->json([
            'message' => 'Tarification log created successfully, balance updated',
            'log' => $log,
        ]);
    }
}