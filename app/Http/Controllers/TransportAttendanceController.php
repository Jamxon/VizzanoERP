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
            // Hozirgi oy va yilni olish
            $currentYear = now()->year;
            $currentMonth = now()->month;

            // Agar 'date' parametri mavjud bo'lsa, shunga mos ravishda filter qilish
            if ($request->has('date')) {
                $date = \Carbon\Carbon::parse($request->date);
                $attendances = TransportAttendance::with('transport') // transport ma'lumotlarini ham olish
                ->whereYear('date', $date->year)
                    ->whereMonth('date', $date->month)
                    ->orderBy('date', 'desc')
                    ->paginate(10);
            } else {
                // Agar 'date' parametri bo'lmasa, joriy oy va yilga mos ma'lumotlarni olish
                $attendances = TransportAttendance::with('transport') // transport ma'lumotlarini ham olish
                ->whereYear('date', $currentYear)
                    ->whereMonth('date', $currentMonth)
                    ->orderBy('date', 'desc')
                    ->paginate(10);
            }

            // Resurs yordamida chiroyli ko'rinishda natija qaytarish
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
            'transport_id' => 'required|exists:transports,id',
            'date' => 'required|date',
            'attendance_type' => 'required|in:0,0.5,1',
            'salary' => 'nullable|numeric',
            'fuel_bonus' => 'nullable|numeric',
        ]);

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

            return response()->json(['error' => 'Davomat qo\'shishda xatolik yuz berdi'], 500);
        }
    }

    public function update(Request $request, $id): \Illuminate\Http\JsonResponse
    {
        $year = date('Y', strtotime($request->date));
        $month = date('m', strtotime($request->date));

        if (MonthlyClosure::isMonthClosed($year, $month)) {
            return response()->json(['error' => 'Bu oyga davomatni yangilash mumkin emas. Oyning yopilishi amalga oshirilgan.'], 400);
        }

        $request->validate([
            'transport_id' => 'required|exists:transports,id',
            'date' => 'required|date',
            'attendance_type' => 'required|in:0,0.5,1',
            'salary' => 'nullable|numeric',
            'fuel_bonus' => 'nullable|numeric',
        ]);

        try {
            $attendance = TransportAttendance::findOrFail($id);
            $attendance->update($request->all());
            return response()->json(['message' => 'Davomat muvaffaqiyatli yangilandi', 'data' => $attendance], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Davomatni yangilashda xatolik yuz berdi'], 500);
        }
    }

}
