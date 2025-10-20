<?php

namespace App\Console;

use App\Models\Log;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('database:backup')->dailyAt('02:00');
        $schedule->command('attendance:mark-absent')->dailyAt('23:50');
        $schedule->command('attendance:mark-left')->dailyAt('17:30');
        $schedule->command('kpi:calculate')->monthlyOn(Carbon::now()->endOfMonth()->day, '23:55');
        $schedule->command('monitoring:report')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
