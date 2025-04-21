<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Log;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public  function getAttendances(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = $request->date ?? now()->toDateString();
        $attendances = Attendance::whereDate('date', $date)
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($attendances);
    }

    public function getAttendanceHistory(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = $request->date ?? now()->toDateString();
        $attendances = Attendance::where('date', $date)
            ->with('employee')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($attendances);
    }

    public function storeAttendance(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'check_in' => 'required|date',
        ]);

        $today = now()->toDateString();
        // Shu kunga allaqachon kelganmi?
        $existing = Attendance::where('employee_id', $request->employee_id)
            ->whereDate('date', $today)
            ->first();

        if ($existing && $existing->check_in) {
            return response()->json([
                'message' => "Bu xodim allaqachon davomatdan o'tgan",
                'data' => $existing,
            ], 200);
        }

        $attendance = Attendance::updateOrCreate(
            [
                'employee_id' => $request->employee_id,
                'date' => $today,
            ],
            [
                'check_in' => $request->check_in,
                'check_out' => null,
                'status' => "present",
            ]
        );

        Log::add(
            auth()->user()->id,
            'Hodim ishga keldi',
            'Check In',
            null,
            [
                'employee_id' => $attendance->employee_id,
                'check_in' => $attendance->check_in,
                'check_out' => $attendance->check_out,
            ]
        );

        return response()->json($attendance->with('employee'));
    }

    public function updateAttendance(Request $request, Attendance $attendance): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'check_out' => 'required|date',
        ]);

        $attendance->update([
            'check_out' => $request->check_out,
        ]);

        Log::add(
            auth()->user()->id,
            'Hodim ishdan ketdi',
            'Check Out',
            null,
            [
                'employee_id' => $attendance->employee_id,
                'check_in' => $attendance->check_in,
                'check_out' => $attendance->check_out,
            ]
        );

        return response()->json($attendance);
    }
}
