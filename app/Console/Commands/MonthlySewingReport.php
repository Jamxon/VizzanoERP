<?php

namespace App\Console\Commands;

use App\Models\CuttingPlan;
use App\Models\OrderCut;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\GroupPlan;
use App\Models\EmployeeSalary;
use App\Models\Log;

class MonthlySewingReport extends Command
{
    protected $signature = 'kpi:calculate';
    protected $description = 'Har oyning oxirida KPI hisoblab, bonuslarni qo\'shadi';

    public function handle(): void
    {
        $now = Carbon::now();
        $month = $now->month;
        $year = $now->year;

        $this->calculateSewingKPI($month, $year);
        $this->calculateCuttingKPI($month, $year);

        $this->info("KPI hisoblandi va bonuslar qo'shildi: {$month}/{$year}");
    }

    protected function calculateSewingKPI($month, $year): void
    {
        $plans = GroupPlan::with([
            'group.employees',
            'group.orders.order.orderModel.submodels.sewingOutputs' => function ($query) use ($month, $year) {
                $query->whereBetween('created_at', [
                    Carbon::create($year, $month, 1)->startOfMonth(),
                    Carbon::create($year, $month, 1)->endOfMonth(),
                ]);
            }
        ])->where('month', $month)->where('year', $year)->get();

        $totalPlan = $plans->sum('quantity');
        $totalActual = 0;

        foreach ($plans as $plan) {
            foreach ($plan->group->orders as $groupOrder) {
                foreach ($groupOrder->order->orderModel->submodels as $submodel) {
                    $totalActual += $submodel->sewingOutputs->sum('quantity');
                }
            }
        }

        $overallPercent = $totalPlan > 0 ? round(($totalActual / $totalPlan) * 100, 2) : 0;

        foreach ($plans as $plan) {
            $planActual = 0;
            foreach ($plan->group->orders as $groupOrder) {
                foreach ($groupOrder->order->orderModel->submodels as $submodel) {
                    $planActual += $submodel->sewingOutputs->sum('quantity');
                }
            }

            $planPercent = $plan->quantity > 0 ? round(($planActual / $plan->quantity) * 100, 2) : 0;

            foreach ($plan->group->employees as $employee) {
                if (!in_array($employee->payment_type, ['fixed_percentage_bonus', 'fixed_percentage_bonus_group'])) continue;

                $percent = $employee->payment_type === 'fixed_percentage_bonus_group'
                    ? $planPercent : $overallPercent;

                $planValue = $employee->payment_type === 'fixed_percentage_bonus_group'
                    ? $plan->quantity : $totalPlan;

                $actualValue = $employee->payment_type === 'fixed_percentage_bonus_group'
                    ? $planActual : $totalActual;

                if ($percent < 80) continue;

                $bonusPercent = $percent >= 100
                    ? $employee->bonus + ($percent - 100)
                    : 10 + (($employee->bonus - 10) * (($percent - 80) / 20));

                $amount = round($employee->salary * ($bonusPercent / 100), 2);
                if ($amount <= 0) continue;

                EmployeeSalary::create([
                    'employee_id' => $employee->id,
                    'month' => $month,
                    'year' => $year,
                    'type' => 'kpi_sewing',
                    'amount' => $amount,
                ]);

                $employee->increment('balance', $amount);

                Log::add($employee->user_id, 'Tikuv KPI hisoblandi', 'salary_bonus', null, [
                    'payment_type' => $employee->payment_type,
                    'plan' => $planValue,
                    'bajarilgan' => $actualValue,
                    'foiz' => $percent,
                    'bonus_foiz' => round($bonusPercent, 2),
                    'summasi' => $amount,
                    'oy' => $month,
                    'yil' => $year,
                ]);
            }
        }

        $this->info("Tikuv KPI: Plan {$totalPlan}, Bajarilgan {$totalActual}");
    }

    protected function calculateCuttingKPI($month, $year): void
    {
        $plans = CuttingPlan::with(['department'])->where('month', $month)->where('year', $year)->get();

        $totalPlan = $plans->sum('quantity');

        $totalActual = OrderCut::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('quantity');

        $overallPercent = $totalPlan > 0 ? round(($totalActual / $totalPlan) * 100, 2) : 0;

        foreach ($plans as $plan) {
            $planActual = OrderCut::whereHas('employee', function ($q) use ($plan) {
                $q->where('department_id', $plan->department_id);
            })
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->sum('quantity');

            $planPercent = $plan->quantity > 0 ? round(($planActual / $plan->quantity) * 100, 2) : 0;

            foreach ($plan->department->employees as $employee) {
                // Cutting KPI uchun alohida type tekshirish (misol uchun: cutting_bonus)
                if ($employee->payment_type !== 'cutting_bonus') continue;

                $percent = $planPercent;
                $planValue = $plan->quantity;
                $actualValue = $planActual;

                if ($percent < 80) continue;

                $bonusPercent = $percent >= 100
                    ? $employee->bonus + ($percent - 100)
                    : 10 + (($employee->bonus - 10) * (($percent - 80) / 20));

                $amount = round($employee->salary * ($bonusPercent / 100), 2);
                if ($amount <= 0) continue;

                EmployeeSalary::create([
                    'employee_id' => $employee->id,
                    'month' => $month,
                    'year' => $year,
                    'type' => 'kpi_cutting',
                    'amount' => $amount,
                ]);

                $employee->increment('balance', $amount);

                Log::add($employee->user_id, 'Kesish KPI hisoblandi', 'salary_bonus', null, [
                    'payment_type' => $employee->payment_type,
                    'plan' => $planValue,
                    'bajarilgan' => $actualValue,
                    'foiz' => $percent,
                    'bonus_foiz' => round($bonusPercent, 2),
                    'summasi' => $amount,
                    'oy' => $month,
                    'yil' => $year,
                ]);
            }
        }

        $this->info("Kesish KPI: Plan {$totalPlan}, Bajarilgan {$totalActual}");
    }

}