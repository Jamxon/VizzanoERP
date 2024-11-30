<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function check_in(Request $request)
    {
        $request->validate([
            'employee_id' => 'required',
            'check_in' => 'required',
        ]);

        $attendance = AttendanceController::create([
            'employee_id' => $request->employee_id,
            'date' => date('Y-m-d'),
            'check_in' => $request->check_in,
        ]);

        if ($attendance) {
            return response()->json([
                'message' => 'Check in successful',
                'attendance' => $attendance,
            ]);
        } else {
            return response()->json([
                'message' => 'Check in failed',
                'error' => $attendance->errors(),
            ], 500);
        }
    }

    public function check_out(Request $request)
    {
        $request->validate([
            'employee_id' => 'required',
            'check_out' => 'required',
        ]);

        $attendance = AttendanceController::where('employee_id', $request->employee_id)
            ->where('date', date('Y-m-d'))
            ->first();

        if ($attendance) {
            $attendance->check_out = $request->check_out;
            $attendance->work_time = strtotime($attendance->check_out) - strtotime($attendance->check_in);
            $attendance->save();

            return response()->json([
                'message' => 'Check out successful',
                'attendance' => $attendance,
            ]);
        } else {
            return response()->json([
                'message' => 'Check out failed',
                'error' => $attendance->errors(),
            ], 404);
        }
    }
}
