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
            'name' => $this->name,
            'username' => $this->user->username,
            'phone' => $this->phone,
            'group' => $this->group->name ?? null,
            'department' => $this->department->name ?? null,
            'hiring_date' => $this->hiring_date,
            'address' => $this->address,
            'payment_type' => $this->payment_type,
            'passport_number' => $this->passport_number,
            'status' => $this->status,
            'img' => $this->img,
            'passport_code' => $this->passport_code,
            'type' => $this->type,
            'birthday' => $this->birthday,
            'position' => $this->position->name ?? null
        ];
    }
}
