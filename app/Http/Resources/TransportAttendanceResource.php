<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class TransportAttendanceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'date'            => $this->date ? $this->date->format('Y-m-d') : null,
            'attendance_type' => $this->attendance_type,
            'salary'          => $this->salary,
            'fuel_bonus'      => $this->fuel_bonus,

            'transport' => $this->whenLoaded('transport', function () {
                if (!$this->transport) {
                    return null;
                }

                return [
                    'id'   => $this->transport->id,
                    'name' => $this->transport->name,

                    'employees' => $this->transport->employees->map(function ($employee) {
                        // shu kuni transport daily yozuvini tekshiramiz
                        $daily = $employee->dailyEmployees()
                            ->whereDate('date', $this->date)
                            ->first();

                        return [
                            'id'   => $employee->id,
                            'name' => $employee->name,
                            'attendance_status' => DB::table('attendance')
                                    ->where('employee_id', $employee->id)
                                    ->whereDate('date', $this->date)
                                    ->value('status') ?? 'absent',
                            'transport_status'  => $daily ? 'present' : 'absent',
                        ];
                    })->values(),
                ];
            }),
        ];
    }
}
