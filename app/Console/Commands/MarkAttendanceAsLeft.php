<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarkAttendanceAsLeft extends Command
{
    protected $signature = 'attendance:mark-left';
    protected $description = 'PRESENT attendancelarni KETDI qilib o‘zgartiradi (17:30 da)';

    public function handle()
    {
        $today = Carbon::today()->toDateString();

        $updated = Attendance::where('date', $today)
            ->where('status', 'present')
            ->update(['check_out' => Carbon::now()]);

        $this->info("✅ $updated ta attendance statusi KETDI ga o‘zgartirildi.");
    }
}