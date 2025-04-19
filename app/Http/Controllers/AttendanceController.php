<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetEmployeeResource;
use App\Models\Attendance;
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
}
