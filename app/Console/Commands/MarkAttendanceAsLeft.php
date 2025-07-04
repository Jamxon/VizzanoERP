<?php

namespace App\Console\Commands;

use App\Helpers\TelegramHelper;
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
        $errors = [];
        $logs = [];

        foreach ($attendances as $attendance) {
            $employee = $attendance->employee;
            $salaryToAdd = 0;

            $attendance->check_out = $now;
            $attendance->save();

            if ($employee->payment_type === 'monthly' || $employee->payment_type === 'fixed_tailored_bonus' || $employee->payment_type === 'fixed_cutted_bonus' || $employee->payment_type === 'fixed_tailored_bonus_group') {
                $salaryToAdd = $employee->salary / 26;
            } elseif ($employee->payment_type === 'daily') {
                $salaryToAdd = $employee->salary;
            } elseif ($employee->payment_type === 'hourly') {
                try {
                    $checkIn = Carbon::parse($attendance->check_in);
                    $workedHours = $now->diffInHours($checkIn);
                    $salaryToAdd = $employee->salary * $workedHours;
                } catch (\Exception $e) {
                    $errors[] = "⚠️ {$employee->full_name} (ID: {$attendance->id}) — xatolik: _" . $e->getMessage() . "_";
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

            $logs[] = "✅ *{$employee->full_name}* — {$salaryToAdd} so‘m";
            $count++;
        }

        $message = "*📋 Check Out Yakunlandi*\n";
        $message .= "📅 Sana: `$today`\n";
        $message .= "👥 Jami: *$count* kishi chiqdi.\n\n";

        if (!empty($logs)) {
            $message .= "*Hisobot:*\n";
            $message .= implode("\n", $logs) . "\n\n";
        }

        if (!empty($errors)) {
            $message .= "*Xatoliklar:*\n";
            $message .= implode("\n", $errors) . "\n";
        }

        TelegramHelper::sendMessage($message);

        $this->info("✅ $count ta attendance qayd etildi va balans yangilandi.");
    }

}