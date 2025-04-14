<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransportAttendanceResource;
use App\Models\Log;
use App\Models\MonthlyClosure;
use App\Models\Transport;
use App\Models\TransportAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class TransportAttendanceController extends Controller
{
    public function index(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        try {
            $currentYear = now()->year;
            $currentMonth = now()->month;

            if ($request->has('date')) {
                $date = \Carbon\Carbon::parse($request->date);
                $attendances = TransportAttendance::with('transport')
                ->whereYear('date', $date->year)
                    ->whereMonth('date', $date->month)
                    ->orderBy('date', 'desc')
                    ->get();
            } else {
                $attendances = TransportAttendance::with('transport')
                ->whereYear('date', $currentYear)
                    ->whereMonth('date', $currentMonth)
                    ->orderBy('date', 'desc')
                    ->get();
            }

            return TransportAttendanceResource::collection($attendances);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Davomatlarni olishda xatolik yuz berdi: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $date = Carbon::parse($request->date);
        $year = $date->year;
        $month = $date->month;

        if (MonthlyClosure::isMonthClosed($year, $month)) {
            Log::add(
                Auth::id(),
                'Oy yopilgan oyga davomat qo‘shishga urinish',
                'error',
                null,
                [
                    'transport_id' => $request->transport_id,
                    'date' => $request->date
                ]
            );

            return response()->json(['error' => 'Bu oyga davomatni qo\'shish mumkin emas. Oyning yopilishi amalga oshirilgan.'], 400);
        }

        $validated = $request->validate([
            'transport_id' => 'required|exists:transport,id',
            'date' => 'required|date',
            'attendance_type' => 'required|in:0,0.5,1',
        ]);

        $existing = TransportAttendance::where('transport_id', $request->transport_id)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'Bu mashina uchun bu kunga allaqachon davomat yozilgan.'
            ], 409);
        }

        try {

            $transport = Transport::where('id', $request->transport_id)->firstOrFail();

            $salary = $request->has('salary') ? $request->salary : $transport->salary;
            $fuelBonus = $request->has('fuel_bonus') ? $request->fuel_bonus : $transport->fuel_bonus;

            $attendance = TransportAttendance::create([
                'transport_id' => $transport->id,
                'date' => $date->toDateString(),
                'fuel_bonus' => $fuelBonus,
                'salary' => $salary,
                'attendance_type' => $request->attendance_type,
            ]);

            $increment = ($salary + $fuelBonus) * $attendance->attendance_type;

            $oldBalance = $transport->balance;
            $transport->balance += $increment;
            $transport->save();

            Log::add(
                Auth::id(),
                'Davomat qo‘shildi',
                'create',
                null,
                [
                    'attendance' => $attendance,
                    'balance_old' => $oldBalance,
                    'balance_new' => $transport->balance,
                ]
            );

            return response()->json([
                'message' => 'Davomat muvaffaqiyatli qo\'shildi',
                'data' => $attendance
            ], 201);

        } catch (\Exception $e) {
            Log::add(
                Auth::id(),
                'Davomat qo‘shishda xatolik',
                'error',
                null,
                [
                    'message' => $e->getMessage(),
                    'transport_id' => $request->transport_id,
                    'date' => $request->date
                ]
            );

            return response()->json([
                'message' => 'Davomat qo\'shishda xatolik yuz berdi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $attendance = TransportAttendance::findOrFail($id);

        $date = Carbon::parse($request->date ?? $attendance->date);
        $year = $date->year;
        $month = $date->month;

        if (MonthlyClosure::isMonthClosed($year, $month)) {
            Log::add(
                Auth::id(),
                'Yopilgan oy uchun davomatni tahrirlashga urinish',
                'error',
                $attendance,
                ['requested_data' => $request->all()]
            );

            return response()->json(['error' => 'Bu oyga davomatni tahrirlab bo‘lmaydi. Oy yopilgan.'], 400);
        }

        $validated = $request->validate([
            'transport_id' => 'required|exists:transport,id',
            'date' => 'required|date',
            'attendance_type' => 'required|in:0,0.5,1',
        ]);

        $newTransportId = $request->transport_id;
        $newDate = Carbon::parse($request->date)->toDateString();

        $exists = TransportAttendance::where('transport_id', $newTransportId)
            ->whereDate('date', $newDate)
            ->where('id', '!=', $attendance->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'Bu mashina uchun bu kunga allaqachon davomat yozilgan.'
            ], 409);
        }

        try {
            $oldData = $attendance->toArray();

            $transport = Transport::where('id', $newTransportId)->firstOrFail();

            $salary = $request->has('salary') ? $request->salary : $transport->salary;
            $fuelBonus = $request->has('fuel_bonus') ? $request->fuel_bonus : $transport->fuel_bonus;

            $oldSalary = $attendance->salary ?? 0;
            $oldFuelBonus = $attendance->fuel_bonus ?? 0;
            $oldAttendanceType = $attendance->attendance_type ?? 0;

            $oldIncrement = ($oldSalary + $oldFuelBonus) * $oldAttendanceType;
            $newIncrement = ($salary + $fuelBonus) * $request->attendance_type;

            $balanceBefore = $transport->balance;
            $transport->balance = $transport->balance - $oldIncrement + $newIncrement;
            $transport->save();

            $attendance->update([
                'transport_id' => $newTransportId,
                'date' => $newDate,
                'attendance_type' => $request->attendance_type,
                'salary' => $salary,
                'fuel_bonus' => $fuelBonus,
            ]);

            Log::add(
                Auth::id(),
                'Davomat yangilandi',
                'edit',
                $oldData,
                [
                    'new_attendance' => $attendance,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $transport->balance,
                ]
            );

            return response()->json([
                'message' => 'Davomat muvaffaqiyatli yangilandi',
                'data' => $attendance
            ], 200);

        } catch (\Exception $e) {
            Log::add(
                Auth::id(),
                'Davomat yangilashda xatolik',
                'error',
                $attendance,
                [
                    'message' => $e->getMessage(),
                    'requested_data' => $request->all()
                ]
            );

            return response()->json([
                'error' => 'Davomat yangilashda xatolik yuz berdi',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function massStore(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'transport_ids' => 'required|array',
            'transport_ids.*' => 'exists:transport,id',
            'date' => 'required|date',
            'attendance_type' => 'required|in:0,0.5,1',
        ]);

        $date = Carbon::parse($request->date);
        $year = $date->year;
        $month = $date->month;

        if (MonthlyClosure::isMonthClosed($year, $month)) {
            Log::add(
                Auth::id(),
                'Oy yopilgan oyga ommaviy davomat qo‘shishga urinish',
                'error',
                null,
                [
                    'transport_ids' => $request->transport_ids,
                    'date' => $request->date
                ]
            );

            return response()->json(['error' => 'Bu oyga davomatni qo\'shish mumkin emas. Oyning yopilishi amalga oshirilgan.'], 400);
        }

        $created = [];
        $errors = [];

        foreach ($request->transport_ids as $transportId) {
            try {
                $transport = Transport::findOrFail($transportId);

                $salary = $transport->salary;
                $fuelBonus = $transport->fuel_bonus;

                // Agar mavjud bo‘lsa, eski yozuvni o‘chir
                $existing = TransportAttendance::where('transport_id', $transportId)
                    ->whereDate('date', $date->toDateString())
                    ->first();

                if ($existing) {
                    // Eski balansni teskari hisobga olish
                    $decrement = ($existing->salary + $existing->fuel_bonus) * $existing->attendance_type;
                    $transport->balance -= $decrement;
                    $existing->delete();
                }

                // Yangi davomat yaratish
                $attendance = TransportAttendance::create([
                    'transport_id' => $transportId,
                    'date' => $date->toDateString(),
                    'fuel_bonus' => $fuelBonus,
                    'salary' => $salary,
                    'attendance_type' => $request->attendance_type,
                ]);

                $increment = ($salary + $fuelBonus) * $attendance->attendance_type;
                $transport->balance += $increment;
                $transport->save();

                $created[] = $attendance;

                Log::add(
                    Auth::id(),
                    'Ommaviy davomat yozildi (yoki yangilandi)',
                    'create',
                    null,
                    [
                        'transport_id' => $transportId,
                        'attendance' => $attendance,
                        'balance' => $transport->balance,
                    ]
                );

            } catch (\Exception $e) {
                $errors[] = [
                    'transport_id' => $transportId,
                    'error' => $e->getMessage(),
                ];

                Log::add(
                    Auth::id(),
                    'Ommaviy davomatda xatolik',
                    'error',
                    null,
                    [
                        'transport_id' => $transportId,
                        'message' => $e->getMessage()
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Ommaviy davomat yakunlandi',
            'created' => $created,
            'errors' => $errors
        ], 207); // 207 Multi-Status — ba'zilari muvaffaqiyatli, ba'zilari xatolik
    }

}
