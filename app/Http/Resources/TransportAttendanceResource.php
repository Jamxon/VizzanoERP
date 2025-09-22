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

                    'employees' => $this->transport->dailyEmployees
                        ->filter(function ($daily) {
                            return $daily->date->format('Y-m-d') === $this->date->format('Y-m-d');
                        })
                        ->map(function ($daily) {
                            return [
                                'id'   => $daily->employee->id ?? null,
                                'name' => $daily->employee->name ?? null,
                                'attendance_status' => DB::table('attendance')
                                        ->where('employee_id', $daily->employee_id)
                                        ->whereDate('date', $daily->date)
                                        ->value('status') ?? 'absent',
                            ];
                        })->values(),
                ];
            }),
        ];
    }
}
