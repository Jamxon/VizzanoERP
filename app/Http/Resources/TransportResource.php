<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use Carbon\Carbon;

/**
 * @property mixed $payments
 */
class TransportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentDate = Carbon::now();
        $currentYear = $currentDate->year;
        $currentMonth = $currentDate->month;

        return [
            'id' => $this->id ?? null,
            'name' => $this->name ?? null,
            'state_number' => $this->state_number ?? null,
            'driver_full_name' => $this->driver_full_name ?? null,
            'phone' => $this->phone ?? null,
            'phone_2' => $this->phone_2 ?? null,
            'capacity' => $this->capacity ?? null,
            'branch_id' => $this->branch ?? null,
            'region_id' => $this->region ?? null,
            'is_active' => $this->is_active ?? null,
            'salary' => $this->salary ?? null,
            'fuel_bonus' => $this->fuel_bonus ?? null,

            'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
            'balance' => $this->balance ?? 0,
            'distance' => $this->distance ?? 0,

            'payment' => $this->whenLoaded('payments', function () use ($currentYear, $currentMonth) {
                return $this->payments
                    ->where('date', '>=', Carbon::create($currentYear, $currentMonth, 1)->startOfDay())
                    ->where('date', '<=', Carbon::create($currentYear, $currentMonth, 1)->endOfMonth())
                    ->values();
            }),

            'daily_employees' => $this->whenLoaded('dailyEmployees', function () {
                return $this->dailyEmployees->map(function ($item) {
                    // attendance ni shu kunga tekshirish
                    $attendance = $item->employee->attendances
                        ->where('date', $item->date)
                        ->first();

                    return [
                        'id' => $item->id,
                        'date' => $item->date,
                        'employee_name' => $item->employee->name ?? null,
                        'attendance_status' => $attendance ? $attendance->status : 'absent',
                    ];
                });
            }),

            'employees' => $this->whenLoaded('employees', function () {
                return $this->employees->map(function ($employee) {
                    return [
                        'id' => $employee->id,
                        'name' => $employee->name,
                    ];
                });
            }),
        ];
    }
}