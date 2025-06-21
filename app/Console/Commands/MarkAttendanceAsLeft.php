<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class MarkAttendanceAsLeft extends Command
{
    protected $signature = 'attendance:mark-left';
    protected $description = 'PRESENT attendancelarni KETDI qilib o‘zgartiradi (17:30 da)';

    public function handle(): void
    {
        $today = Carbon::today()->toDateString();

        $attendances = Attendance::with('employee')
            ->where('date', $today)
            ->whereNull('check_out')
            ->where('status', 'present')
            ->get();

        $now = Carbon::now();
        $count = 0;

        foreach ($attendances as $attendance) {
            $employee = $attendance->employee;
            $salaryToAdd = 0;

            $attendance->check_out = $now;
            $attendance->status = 'present';
            $attendance->save();

            if ($employee->payment_type === 'monthly') {
                $salaryToAdd = $employee->salary / 26;
            } elseif ($employee->payment_type === 'daily') {
                $salaryToAdd = $employee->salary;
            } elseif ($employee->payment_type === 'hourly') {
                try {
                    $checkIn = Carbon::parse($attendance->check_in);
                    $workedHours = $now->diffInHours($checkIn);
                    $salaryToAdd = $employee->salary * $workedHours;
                } catch (\Exception $e) {
                    $this->error("⚠️ Check-in yoki check-out formati noto‘g‘ri: Attendance ID {$attendance->id}");
                    continue;
                }
            }

            $employee->increment('balance', $salaryToAdd);

            \App\Models\AttendanceSalary::create([
                'employee_id' => $employee->id,
                'attendance_id' => $attendance->id,
                'amount' => $salaryToAdd,
                'date' => $today,
            ]);

            \App\Models\Log::add(
                null,
                'Cron orqali ishdan chiqdi',
                'Check Out',
                null,
                [
                    'employee_id' => $employee->id,
                    'check_in' => $attendance->check_in,
                    'check_out' => $attendance->check_out,
                    'added_salary' => $salaryToAdd,
                ]
            );

            $count++;
        }

        $this->info("✅ $count ta attendance qayd etildi va balans yangilandi.");
    }

}