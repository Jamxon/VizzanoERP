<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\GroupPlan;
use App\Models\EmployeeSalary;
use App\Models\Log;

class MonthlySewingReport extends Command
{
    protected $signature = 'kpi:calculate';
    protected $description = 'Har oyning oxirida KPI hisoblab, bonuslarni qo‘shadi';

    public function handle(): void
    {
        $now = Carbon::now();
        $month = $now->month;
        $year = $now->year;

        $plans = GroupPlan::with([
            'group.employees',
            'group.orders.order.orderModel.submodels.sewingOutputs' => function ($query) use ($month, $year) {
                $query->whereBetween('created_at', [
                    Carbon::create($year, $month, 1)->startOfMonth(),
                    Carbon::create($year, $month, 1)->endOfMonth(),
                ]);
            }
        ])->where('month', $month)->where('year', $year)->get();

        // Umumiy plan va actual
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
            // Shu plan uchun bajarilgan ishlarni hisoblaymiz
            $planActual = 0;
            foreach ($plan->group->orders as $groupOrder) {
                foreach ($groupOrder->order->orderModel->submodels as $submodel) {
                    $planActual += $submodel->sewingOutputs->sum('quantity');
                }
            }

            $planPercent = $plan->quantity > 0 ? round(($planActual / $plan->quantity) * 100, 2) : 0;

            foreach ($plan->group->employees as $employee) {
                if (!in_array($employee->payment_type, ['fixed_percentage_bonus', 'fixed_percentage_bonus_group'])) {
                    continue;
                }

                $percent = $employee->payment_type === 'fixed_percentage_bonus_group'
                    ? $planPercent
                    : $overallPercent;

                if ($percent < 80) {
                    continue;
                }

                $bonusPercent = 0;

                if ($percent >= 100) {
                    $bonusPercent = $employee->bonus + ($percent - 100);
                } else {
                    $baseBonus = 10;
                    $additional = $employee->bonus - $baseBonus;
                    $scale = ($percent - 80) / 20;
                    $bonusPercent = $baseBonus + ($additional * $scale);
                }

                $amount = round($employee->salary * ($bonusPercent / 100), 2);

                if ($amount <= 0) {
                    continue;
                }

                EmployeeSalary::create([
                    'employee_id' => $employee->id,
                    'month' => $month,
                    'year' => $year,
                    'type' => 'kpi',
                    'amount' => $amount,
                ]);

                Log::add(
                    $employee->user_id,
                    'KPI hisoblandi',
                    'salary_bonus',
                    null,
                    [
                        'plan' => $employee->payment_type === 'fixed_percentage_bonus_group' ? $plan->quantity : $totalPlan,
                        'bajarilgan' => $employee->payment_type === 'fixed_percentage_bonus_group' ? $planActual : $totalActual,
                        'foiz' => $percent,
                        'bonus_foiz' => round($bonusPercent, 2),
                        'summasi' => $amount,
                        'oy' => $month,
                        'yil' => $year,
                    ]
                );
            }
        }

        $this->info("KPI hisoblandi va bonuslar qo‘shildi: {$month}/{$year}");
    }
}
