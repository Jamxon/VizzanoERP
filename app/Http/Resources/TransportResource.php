<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'vin_number' => $this->vin_number ?? null,
            'tech_passport_number' => $this->tech_passport_number ?? null,
            'engine_number' => $this->engine_number ?? null,
            'year' => $this->year ?? null,
            'color' => $this->color ?? null,

            // 🛠️ Formatted Dates
            'registration_date' => optional($this->registration_date)->format('Y-m-d'),
            'insurance_expiry' => optional($this->insurance_expiry)->format('Y-m-d'),
            'inspection_expiry' => optional($this->inspection_expiry)->format('Y-m-d'),

            'driver_passport_number' => $this->driver_passport_number ?? null,
            'driver_license_number' => $this->driver_license_number ?? null,
            'driver_experience_years' => $this->driver_experience_years ?? null,
            'salary' => $this->salary ?? null,
            'fuel_bonus' => $this->fuel_bonus ?? null,

            // ⏱️ Created at datetime format
            'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),

            'balance' => $this->balance ?? 0,
            'distance' => $this->distance ?? 0,
        ];
    }
}
