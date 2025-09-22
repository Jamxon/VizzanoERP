<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $attendance_type
 * @property mixed $salary
 * @property mixed $date
 * @property mixed $id
 * @property mixed $fuel_bonus
 * @property mixed $method
 * @property mixed $transport
 */
class TransportAttendanceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'   => $this->id,
            'date' => $this->date,

            'transport' => $this->whenLoaded('transport', function () {
                if (!$this->transport) {
                    return null;
                }

                return [
                    'id'   => $this->transport->id,
                    'name' => $this->transport->name,

                    'employees' => $this->transport->dailyEmployees
                        ->where('date', $this->date)
                        ->map(function ($daily) {
                            return [
                                'id'   => $daily->employee->id ?? null,
                                'name' => $daily->employee->name ?? null,
                                'attendance_status' => \DB::table('attendance')
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
