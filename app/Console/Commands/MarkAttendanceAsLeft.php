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

            try {
                if (in_array($employee->payment_type, ['monthly', 'fixed_tailored_bonus', 'fixed_cutted_bonus', 'fixed_tailored_bonus_group'])) {
                    $salaryToAdd = $employee->salary / 26;
                } elseif ($employee->payment_type === 'daily') {
                    $salaryToAdd = $employee->salary;
                } elseif ($employee->payment_type === 'hourly') {
                    $checkIn = Carbon::parse($attendance->check_in);
                    if ($checkIn->greaterThan($now)) {
                        throw new \Exception("check_in vaqt noto‘g‘ri (kelajakda)");
                    }
                    $workedHours = $now->diffInHours($checkIn);
                    if ($workedHours > 24) {
                        throw new \Exception("ishlagan soat 24 soatdan oshib ketgan: $workedHours");
                    }
                    $salaryToAdd = $employee->salary * $workedHours;
                }

                // Cheklovdan katta bo‘lsa yozmaslik
                if ($salaryToAdd > 9999999999.99) {
                    throw new \Exception("Juda katta miqdor: $salaryToAdd");
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

                $logs[] = "✅ *{$employee->full_name}* — `{$salaryToAdd}` so‘m";
                $count++;
            } catch (\Exception $e) {
                $errors[] = "⚠️ *{$employee->full_name}* (ID: {$attendance->id}) — " . $e->getMessage();
                continue;
            }
        }

        // Yakuniy xabar
        $message = "*📋 Check Out Yakunlandi*\n";
        $message .= "📅 Sana: `$today`\n";
        $message .= "👥 Jami: *$count* xodim uchun qayd etildi*\n\n";

        if (!empty($logs)) {
            $message .= "*Hisobot:*\n";
            $message .= implode("\n", $logs) . "\n\n";
        }

        if (!empty($errors)) {
            $message .= "*❗️ Xatoliklar:*\n";
            $message .= implode("\n", $errors) . "\n";
        }

        TelegramHelper::sendMessage($message);
        $this->info("✅ $count ta attendance qayd etildi va balans yangilandi.");
    }

}