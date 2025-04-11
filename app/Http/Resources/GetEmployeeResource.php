<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $passport_number
 * @property mixed $passport_code
 * @property mixed $salary
 * @property mixed $type
 * @property mixed $birthday
 * @property mixed $payment_type
 * @property mixed $img
 * @property mixed $status
 * @property mixed $address
 * @property mixed $hiring_date
 * @property mixed $department
 * @property mixed $group
 * @property mixed $role
 * @property mixed $phone
 * @property mixed $name
 * @property mixed $user
 * @property mixed $id
 * @property mixed $username
 * @property mixed $department_id
 * @property mixed $position
 */

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
            'name' => $this->name ?? null,
            'user' => [
                'username' => $this->user->username ?? null,
                'role' => $this->user->role ?? null,
            ],
            'phone' => $this->phone ?? null,
            'group' => $this->group ?? null,
            'department' => $this->department ?? null,
            'hiring_date' => $this->hiring_date ?? null,
            'address' => $this->address ?? null,
            'payment_type' => $this->payment_type ?? null,
            'passport_number' => $this->passport_number ?? null,
            'status' => $this->status ?? null,
            'img' => $this->img ?? null,
            'passport_code' => $this->passport_code ?? null,
            'type' => $this->type ?? null,
            'birthday' => $this->birthday ?? null,
            'position' => $this->position->name ?? null
        ];
    }
}
