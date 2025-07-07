<?php

namespace App\Console\Commands;

use App\Models\GroupPlan;
use App\Models\Log;
use App\Models\EmployeeSalary;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonthlySewingReport extends Command
{
    protected $signature = 'kpi:calculate';
    protected $description = 'Har oyning oxirida KPI ni hisoblab bonuslarni qoshadi';

    public function handle(): void
    {
        $now = Carbon::now();
        $month = $now->month;
        $year = $now->year;

        $plans = GroupPlan::with([
            'group.employees',
            'group.orders.order.orderModel.submodels.sewingOutputs' => function ($q) use ($month, $year) {
                $q->whereBetween('created_at', [
                    Carbon::create($year, $month, 1)->startOfMonth(),
                    Carbon::create($year, $month, 1)->endOfMonth(),
                ]);
            }
        ])->where('month', $month)->where('year', $year)->get();

        foreach ($plans as $plan) {
            $group = $plan->group;
            $expected = $plan->total;

            $actual = 0;
            foreach ($group->orders as $groupOrder) {
                foreach ($groupOrder->order->orderModel->submodels as $submodel) {
                    $actual += $submodel->sewingOutputs->sum('actual');
                }
            }

            if ($expected <= 0) {
                continue; // bo‘sh planlar o‘tkazib yuboriladi
            }

            $percent = round($actual / $expected * 100, 2); // Bajarilgan foiz

            // Har bir employee uchun KPI hisoblaymiz
            foreach ($group->employees as $employee) {
                if ($employee->payment_type !== 'fixed_percentage_bonus' &&
                    $employee->payment_type !== 'fixed_percentage_bonus_group') {
                    continue; // bu tipda bo‘lmaganlarni o‘tkazib yuboramiz
                }

                if ($percent < 80) {
                    continue; // 80% dan kam bajarganlarga bonus yo‘q
                }

                $bonusPercent = 0;

                if ($percent >= 100) {
                    $bonusPercent = $employee->bonus;
                } else {
                    $baseBonus = 10; // minimum 80% uchun
                    $additionalBonus = $employee->bonus - $baseBonus;
                    $scale = ($percent - 80) / 20;
                    $bonusPercent = $baseBonus + ($additionalBonus * $scale);
                }

                $amount = round($employee->salary * ($bonusPercent / 100), 2);

                // Bonus ma'lumotlarini employee_salaries jadvaliga yozamiz
                EmployeeSalary::create([
                    'employee_id' => $employee->id,
                    'month' => $month,
                    'year' => $year,
                    'type' => 'kpi',
                    'amount' => $amount,
                ]);

                // Log yozish
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

        $this->info("KPI hisoblandi va bonuslar qo'shildi ($month/$year)");
    }
}
