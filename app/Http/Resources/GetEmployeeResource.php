<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GetEmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'group' => $this->group->name,
            'department' => $this->group->department->name,
            'payment_type' => $this->payment_type,
            'salary' => $this->salary,
            'hiring_date' => $this->hiring_date,
        ];
    }
}
