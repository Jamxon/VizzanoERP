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
        ])
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        foreach ($plans as $plan) {
            $expected = $plan->quantity;
            $actual = 0;

            foreach ($plan->group->orders as $groupOrder) {
                foreach ($groupOrder->order->orderModel->submodels as $submodel) {
                    $actual += $submodel->sewingOutputs->sum('quantity');
                }
            }

            // Plan 0 bo‘lsa division error chiqmasligi uchun
            if ($expected <= 0) {
                continue;
            }

            $percent = round(($actual / $expected) * 100, 2);

            foreach ($plan->group->employees as $employee) {
                if (!in_array($employee->payment_type, ['fixed_percentage_bonus', 'fixed_percentage_bonus_group'])) {
                    continue;
                }

                if ($percent < 80) {
                    continue;
                }

                $bonusPercent = 0;

                if ($percent >= 100) {
                    $bonusPercent = $employee->bonus + ($percent - 100); // ortiqcha % ham bonusga qo‘shiladi
                } else {
                    $baseBonus = 10;
                    $additional = $employee->bonus - $baseBonus;
                    $scale = ($percent - 80) / 20;
                    $bonusPercent = $baseBonus + ($additional * $scale);
                }

                $amount = round($employee->salary * ($bonusPercent / 100), 2);

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
                        'plan' => $expected,
                        'bajarilgan' => $actual,
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
