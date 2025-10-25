<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSalary;
use App\Models\Employee;
use App\Models\EmployeeTransportDaily;
use App\Models\Log;
use Carbon\Carbon;
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
            'check_in' => 'nullable|date',
            'date' => 'nullable|date',
            'status' => 'nullable|string|in:present,absent',
        ]);

        $today = $request->date ?? now()->toDateString();

        // Eski yozuvni olish (keyin status o'zgarganini tekshirish uchun)
        $existing = Attendance::where('employee_id', $request->employee_id)
            ->whereDate('date', $today)
            ->first();

        // Agar eski yozuv "present" bo'lib, yangi status "absent" bo'lsa
        if ($existing && $existing->status === 'present') {
            // AttendanceSalary topamiz
            $salaryRecord = AttendanceSalary::where('employee_id', $request->employee_id)
                ->whereDate('date', $today)
                ->first();

            if ($salaryRecord) {
                // Hodim balansidan ayirish
                $employee = Employee::find($request->employee_id);
                if ($employee) {
                    $employee->balance -= $salaryRecord->amount; // yoki boshqa balans maydoni bo'lsa shuni ishlating
                    $employee->save();
                }

                // Salary yozuvini o'chirish
                $salaryRecord->delete();
            }
        }

        // Attendance yozuvini yangilash yoki yaratish
        $attendance = Attendance::updateOrCreate(
            [
                'employee_id' => $request->employee_id,
                'date' => $today,
            ],
            [
                'check_in' => $request->check_in ?? null,
                'check_out' => null,
                'status' => $request->status ?? 'present',
            ]
        );

        // ✅ Agar status "present" bo‘lsa transport davomatini ham yozamiz
        if ($attendance->status === 'present') {
            $employee = Employee::with('transports')->find($attendance->employee_id);

            if ($employee && $employee->transports->isNotEmpty()) {
                $transport = $employee->transports->first();

                $exists = EmployeeTransportDaily::where('employee_id', $employee->id)
                    ->whereDate('date', $today)
                    ->exists();

                if (!$exists) {
                    EmployeeTransportDaily::create([
                        'employee_id' => $employee->id,
                        'transport_id' => $transport->id,
                        'date' => $today,
                    ]);
                }
            }

            if ($employee && $employee->type === 'aup' && $attendance->check_in) {
                $checkInTime = Carbon::parse($attendance->check_in, 'Asia/Tashkent');
                $lateTime = Carbon::createFromTime(7, 30, 0, 'Asia/Tashkent');

                if ($checkInTime->gt($lateTime)) {
                    $lateChatId = -4832517980; // AUP kechikishlar uchun chat ID
                    $botToken = '8466233197:AAFpW34maMs_2y5-Ro_2FQNxniLBaWwLRD8';

                    $msg = sprintf(
                        "⚠️ *%s %s* (AUP) kechikib keldi.\n🕒 %s\n🏢 Bo‘lim: %s\n👥 Guruh: %s",
                        $employee->name ?? '',
                        $employee->phone ?? '',
                        $checkInTime->format('H:i:s'),
                        $employee->department->name ?? '-',
                        $employee->group->name ?? '-'
                    );

                    // fon jarayon sifatida yuborish
                    dispatch(function () use ($botToken, $lateChatId, $msg) {
                        try {
                            \Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                                'chat_id' => $lateChatId,
                                'text' => $msg,
                                'parse_mode' => 'Markdown',
                            ]);
                        } catch (\Exception $e) {
                            \Log::error('Telegram kechikish xabar yuborilmadi: ' . $e->getMessage());
                        }
                    });
                }
            }
        }



        try {
            $employee = Employee::with(['department', 'group'])->find($attendance->employee_id);

            if ($employee) {
                $branchId = $employee->branch_id;

                // Shu filialdagi barcha hodimlarni olish
                $today = Carbon::today();

                $employees = Employee::with(['department', 'group'])
                    ->where('branch_id', $branchId)
                    ->whereHas('attendances', function ($query) use ($today) {
                        $query->whereDate('check_in', $today);
                    })
                    ->get();

                // Chat ID ni DB yoki configdan olish kerak (hozircha branchga bog‘lab yozaylik)
                $chatId = match ($branchId) {
                    4 => -1001457275928, // masalan 4-branch uchun chat_id
                    5 => -1001883536528, // misol uchun branch 5 gruppa ID
                    default => null,
                };

                if ($chatId) {
                    app(\App\Services\TelegramService::class)
                        ->updateDailyReport($branchId, $chatId, $employees);
                }
            }
        } catch (\Throwable $e) {
            \Log::error("Telegram report update failed: " . $e->getMessage());
        }

        Log::add(
            auth()->user()->id,
            'Davomat yozildi',
            'Attendance',
            null,
            [
                'employee_id' => $attendance->employee_id,
                'check_in' => $attendance->check_in,
                'check_out' => $attendance->check_out,
                'status' => $attendance->status
            ]
        );

        return response()->json($attendance);
    }

    public function updateAttendance(Request $request, Attendance $attendance): \Illuminate\Http\JsonResponse
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

            if ($employee->payment_type === 'monthly' || $employee->payment_type === 'fixed_tailored_bonus' || $employee->payment_type === 'fixed_cutted_bonus' || $employee->payment_type === 'fixed_tailored_bonus_group' || $employee->payment_type === 'fixed_packaged_bonus'
            || $employee->payment_type === 'fixed_completed_bonus'
                || $employee->payment_type === 'fixed_percentage_bonus' || $employee->payment_type === 'cutting_bonus' )
            {
                $salaryToAdd = $employee->salary / 26;
            } elseif ($employee->payment_type === 'daily') {
                $salaryToAdd = $employee->salary;
            } elseif ($employee->payment_type === 'hourly') {
                try {
                    $checkIn = \Carbon\Carbon::parse($attendance->check_in);
                    $checkOut = \Carbon\Carbon::parse($attendance->check_out);

                    // 🔎 Agar 8:00 gacha bo'lsa => 7:30 ga tenglashtiramiz
                    if ($checkIn->lt($checkIn->copy()->setTime(8, 0))) {
                        $checkIn->setTime(7, 30);
                    }

                    $workedSeconds = $checkOut->diffInSeconds($checkIn);
                    $workedHours = $workedSeconds / 3600;

                    $salaryToAdd = $employee->salary * $workedHours;

                } catch (\Exception $e) {
                    return response()->json(['error' => 'Check-in yoki check-out noto‘g‘ri formatda.'], 422);
                }

            } elseif ( $employee->payment_type === 'fixed_percentage_bonus_group')
            {
                try {
                    $checkIn = \Carbon\Carbon::parse($attendance->check_in);
                    $checkOut = \Carbon\Carbon::parse($attendance->check_out);

                    // 🔎 Agar 8:00 gacha bo'lsa => 7:30 ga tenglashtiramiz
                    if ($checkIn->lt($checkIn->copy()->setTime(8, 0))) {
                        $checkIn->setTime(7, 30);
                    }

                    $workedSeconds = $checkOut->diffInSeconds($checkIn);
                    $workedHours = $workedSeconds / 3600;

                    $salary = ($employee->salary / 26) / 10;

                    $salaryToAdd = $salary * $workedHours;

                } catch (\Exception $e) {
                    return response()->json(['error' => 'Check-in yoki check-out noto‘g‘ri formatda.'], 422);
                }
            }

        $attendanceSalary = AttendanceSalary::where('attendance_id', $attendance->id)->first();

        if ($attendanceSalary) {
            $oldAmount = $attendanceSalary->amount;
            $difference = $salaryToAdd - $oldAmount;

            // Balansni faqat farqga qarab yangilaymiz
            if ($difference != 0) {
                $employee->increment('balance', $difference);
            }

            $attendanceSalary->update([
                'amount' => $salaryToAdd,
            ]);
        } else {
            // Yangi salary log
            AttendanceSalary::create([
                'employee_id' => $attendance->employee_id,
                'attendance_id' => $attendance->id,
                'amount' => $salaryToAdd,
                'date' => $attendance->date,
            ]);

            // Faqat yangi bo‘lsa balansga qo‘shamiz
            $employee->increment('balance', $salaryToAdd);
        }


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