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
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('Y-m-d'),
            'attendance_type' => $this->attendance_type,
            'salary' => $this->salary,
            'fuel_bonus' => $this->fuel_bonus,
            'method' => $this->method,
            'transport' => [
                'id' => $this->transport->id,
                'name' => $this->transport->name,
                'state_number' => $this->transport->state_number,
            ],
        ];
    }
}
