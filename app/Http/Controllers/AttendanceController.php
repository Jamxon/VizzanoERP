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
            ->whereHas('employee', function ($query) {
                $query->where('branch_id', auth()->user()->employee->branch_id);
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($attendances);
    }

    public function getAttendanceHistory(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = $request->date ?? now()->toDateString();
        $attendances = Attendance::where('date', $date)
            ->whereHas('employee', function ($query) {
                $query->where('branch_id', auth()->user()->employee->branch_id);
            })
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
            ->with('employee')
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

        return response()->json($attendance);
    }

    public function updateAttendance(Request $request, Attendance $attendance)
        {
            $request->validate([
                'check_out' => 'required|date',
            ]);

            $attendance->update([
                'check_out' => $request->check_out,
            ]);

            // check_out bo'lsa, statusni 'present' qilish mantiqan to‘g‘ri
            if ($attendance->check_out) {
                $attendance->status = 'present';
                $attendance->save();
            }

            $employee = $attendance->employee; // faqat 1 marta DB dan olinadi
            $salaryToAdd = 0;

            if ($employee->payment_type === 'monthly') {
                $salaryToAdd = $employee->salary / 26;
            } elseif ($employee->payment_type === 'daily') {
                $salaryToAdd = $employee->salary;
            } elseif ($employee->payment_type === 'hourly') {
                try {
                    $checkIn = \Carbon\Carbon::parse($attendance->check_in);
                    $checkOut = \Carbon\Carbon::parse($attendance->check_out);
                    $workedHours = $checkOut->diffInHours($checkIn);
                    $salaryToAdd = $employee->salary * $workedHours;
                } catch (\Exception $e) {
                    return response()->json(['error' => 'Check-in yoki check-out noto‘g‘ri formatda.'], 422);
                }
            }

            // Endi balansga qo‘shamiz
            $employee->increment('balance', $salaryToAdd);

            Log::add(
                auth()->id(),
                'Hodim ishdan chiqdi',
                'Check Out',
                null,
                [
                    'employee_id' => $attendance->employee_id,
                    'check_in' => $attendance->check_in,
                    'check_out' => $attendance->check_out,
                    'added_salary' => $salaryToAdd,
                ]
            );

            return response()->json([
                'message' => 'Check-out va balans hisoblash muvaffaqiyatli amalga oshirildi.',
                'attendance' => $attendance,
                'added_balance' => $salaryToAdd,
            ]);
    }

}
