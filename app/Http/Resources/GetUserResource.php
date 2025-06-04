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

        if ($this->payment_type === 'fixed_tailored_bonus') {
            $orders = Order::with('orderModel.submodels.sewingOutputs')
                ->where('branch_id', auth()->user()->employee->branch_id)
                ->get();

            foreach ($orders as $order) {
                $minutes = $order->orderModel->rasxod / 250;
                $pricePerOrder = $minutes * 1;

                if (!$order->orderModel) continue;

                foreach ($order->orderModel->submodels as $submodel) {
                    foreach ($submodel->sewingOutputs as $output) {
                        if (
                            Carbon::parse($output->created_at)->isSameDay($today)
                        ) {
                            $todayBonus += $pricePerOrder * $output->quantity;
                        }
                    }
                }
            }
        }
        // ✅ Foydalanuvchi o‘z groupiga biriktirilgan orderlardan bonus hisoblash
        elseif ($this->payment_type === 'fixed_tailored_bonus_group') {
            if ($this->group) {
                $groupId = $this->group->id;
                $orders = Order::with([
                    'orderModel.submodels.sewingOutputs' => function ($query) {
                        $query->whereDate('created_at', Carbon::today());
                    },
                    'orderGroups'
                ])
                    ->where('branch_id', auth()->user()->employee->branch_id)
                    ->whereHas('orderGroups', function ($query) use ($groupId) {
                        $query->where('group_id', $groupId);
                    })
                    ->get();

                dd($orders);

                foreach ($orders as $order) {
                    if (!$order->orderModel) continue;

                    foreach ($order->orderModel->submodels as $submodel) {
                        foreach ($submodel->sewingOutputs as $output) {
                                $minutes = ($output->seconds ?? 0) / 60;
                                $todayBonus += $minutes * 9;
                        }
                    }
                }
            }
        }
        elseif (in_array($this->payment_type, ['monthly', 'hourly', 'daily'])) {
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
