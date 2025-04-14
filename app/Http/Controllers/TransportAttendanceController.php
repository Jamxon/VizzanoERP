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
            'salary' => 'nullable|numeric',
            'fuel_bonus' => 'nullable|numeric',
        ]);

        // 🚨 Tekshiruv: Shu kunga allaqachon attendance yozilganmi?
        $existing = TransportAttendance::where('transport_id', $request->transport_id)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'Bu mashina uchun bu kunga allaqachon davomat yozilgan.'
            ], 409); // 409 Conflict
        }

        try {
            $attendance = TransportAttendance::create($request->only([
                'transport_id', 'date', 'attendance_type', 'salary', 'fuel_bonus'
            ]));

            $transport = Transport::where('id', $attendance->transport_id)->firstOrFail();

            $salary = $attendance->salary ?? 0;
            $fuelBonus = $attendance->fuel_bonus ?? 0;
            $increment = ($salary + $transport->fuel_bonus) * $attendance->attendance_type;

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
            'transport_id' => 'required|exists:transports,id',
            'date' => 'required|date',
            'attendance_type' => 'required|in:0,0.5,1',
            'salary' => 'nullable|numeric',
            'fuel_bonus' => 'nullable|numeric',
        ]);

        try {
            $oldData = $attendance->toArray();

            $attendance->update($request->only([
                'transport_id', 'date', 'attendance_type', 'salary', 'fuel_bonus'
            ]));

            $transport = Transport::where('id', $attendance->transport_id)->firstOrFail();

            $salary = $attendance->salary ?? 0;
            $fuelBonus = $attendance->fuel_bonus ?? 0;

            $balanceBefore = $transport->balance;

            $oldSalary = $oldData['salary'] ?? 0;
            $oldAttendanceType = $oldData['attendance_type'] ?? 0;
            $oldFuelBonus = $oldData['fuel_bonus'] ?? 0;

            $oldIncrement = ($oldSalary + $transport->fuel_bonus) * $oldAttendanceType;
            $newIncrement = ($salary + $transport->fuel_bonus) * $attendance->attendance_type;

            $transport->balance = $transport->balance - $oldIncrement + $newIncrement;
            $transport->save();

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

            return response()->json(['error' => 'Davomat yangilashda xatolik yuz berdi'], 500);
        }
    }

}
