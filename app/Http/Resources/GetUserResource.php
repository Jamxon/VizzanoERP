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
            'dutie' => $this->position->dutie ?? null,
            'payment_type' => $this->payment_type,
            'user_id' => $this->user_id,
            'gender' => $this->gender,
            'balance' => $this->balance,
            'attendance_salaries' => $this->attendanceSalaries,
            'employee_tarification_logs' => $this->employeeTarificationLogs->map(function (EmployeeTarificationLog $log) {
                return [
                    'id' => $log->id,
                    'tarification_id' => $log->tarification_id,
                    'date' => Carbon::parse($log->date)->format('Y-m-d'),
                    'quantity' => $log->quantity,
                    'is_own' => $log->is_own,
                    'amount_earned' => $log->amount_earned,
                    'box_tarification_id' => $log->box_tarification_id,
                    'order' => $log->tarification->tarificationCategory->submodel->orderModel->order ?? null,
                    'model' => $log->tarification->tarificationCategory->submodel->orderModel->model ?? null,
                    'submodel' => $log->tarification->tarificationCategory->submodel->submodel ?? null,
                    'tarification' => $log->tarification,
                ];
            }),
            'attendances' => $this->attendances,
            'holidays' => $this->employeeHolidays,
            'absences' => $this->employeeAbsences,
            'salary' => $this->salary,
            'bonus' => $this->bonus,
            'employeeSalaries' => $this->employeeSalaries,
            'salaryPayments' => $this->salaryPayments ?? null,
            'salary_visible' => $this->salary_visible ?? true,
            'monthlySalaries' => $this->monthlySalaries ?? 0,
            'monthlyPieceworks' => $this->monthlyPieceworks ?? 0,
        ];
    }
}
