<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public  function index()
    {
        $date = request('date', now()->toDateString());
        $attendances = Attendance::whereDate('date', $date)
            ->with(['employee'])
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
