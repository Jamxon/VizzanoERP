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
            'id' => $this->id ?? null,
            'date' => $this->date->format('Y-m-d') ?? null,
            'attendance_type' => $this->attendance_type ?? null,
            'salary' => $this->salary ?? null,
            'fuel_bonus' => $this->fuel_bonus ?? null,
            'method' => $this->method ?? null,
            'transport' => [
                'id' => $this->transport->id ?? null,
                'name' => $this->transport->name ?? null,
                'state_number' => $this->transport->state_number ?? null,
            ],
        ];
    }
}
