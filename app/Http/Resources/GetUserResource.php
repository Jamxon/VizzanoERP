<?php

namespace App\Http\Resources;

use App\Models\AttendanceSalary;
use App\Models\EmployeeTarificationLog;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

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
class GetUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = Carbon::today();

        $todayBonus = 0;

        if (in_array($this->payment_type, ['monthly', 'hourly', 'daily'])) {
            $todayBonus = AttendanceSalary::where('employee_id', $this->id)
                ->where('date', $today->toDateString())
                ->sum('amount');
        } elseif ($this->payment_type === 'piece_work') {
            $todayBonus = EmployeeTarificationLog::where('employee_id', $this->id)
                ->where('date', $today->toDateString())
                ->sum('amount_earned');
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->user->username,
            'phone' => $this->phone,
            'department' => $this->department->name ?? null,
            'group' => $this->group->name ?? null,
            'role' => $this->user->role->name ?? null,
            'hiring_date' => $this->hiring_date,
            'address' => $this->address,
            'passport_number' => $this->passport_number,
            'status' => $this->status,
            'img' => $this->img,
            'passport_code' => $this->passport_code,
            'birthday' => $this->birthday,
            'position' => $this->position->name ?? null,
            'user_id' => $this->user_id,
            'gender' => $this->gender,
            'balance' => $this->balance,
            'today_bonus' => $todayBonus,
        ];
    }
}
