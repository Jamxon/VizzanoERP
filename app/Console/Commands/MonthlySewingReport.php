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
    protected $description = 'Har oyning oxirida KPI hisoblab, bonuslarni qo\'shadi';

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

        // Umumiy plan va actual ni hisoblaymiz
        $totalPlan = $plans->sum('quantity');
        $totalActual = 0;

        foreach ($plans as $plan) {
            foreach ($plan->group->orders as $groupOrder) {
                foreach ($groupOrder->order->orderModel->submodels as $submodel) {
                    $totalActual += $submodel->sewingOutputs->sum('quantity');
                }
            }
        }

        // Umumiy foizni hisoblaymiz
        $overallPercent = $totalPlan > 0 ? round(($totalActual / $totalPlan) * 100, 2) : 0;

        foreach ($plans as $plan) {
            // Har bir group uchun alohida plan va actual hisoblaymiz
            $planActual = 0;
            foreach ($plan->group->orders as $groupOrder) {
                foreach ($groupOrder->order->orderModel->submodels as $submodel) {
                    $planActual += $submodel->sewingOutputs->sum('quantity');
                }
            }

            // Shu group uchun foizni hisoblaymiz
            $planPercent = $plan->quantity > 0 ? round(($planActual / $plan->quantity) * 100, 2) : 0;

            // Shu groupdagi employeelar uchun bonus hisoblaymiz
            foreach ($plan->group->employees as $employee) {
                // Faqat KPI bilan ishlaydiganlar uchun
                if (!in_array($employee->payment_type, ['fixed_percentage_bonus', 'fixed_percentage_bonus_group'])) {
                    continue;
                }

                // Qaysi foizdan foydalanish kerakligini aniqlaymiz
                if ($employee->payment_type === 'fixed_percentage_bonus_group') {
                    // Group natijasidan foydalanish
                    $percent = $planPercent;
                    $planValue = $plan->quantity;
                    $actualValue = $planActual;
                } else {
                    // Umumiy natijadan foydalanish
                    $percent = $overallPercent;
                    $planValue = $totalPlan;
                    $actualValue = $totalActual;
                }

                // 80% dan kam bo'lsa bonus bermaymiz
                if ($percent < 80) {
                    continue;
                }

                $bonusPercent = 0;

                if ($percent >= 100) {
                    // 100% va undan yuqori: asosiy bonus + qo'shimcha
                    $bonusPercent = $employee->bonus + ($percent - 100);
                } else {
                    // 80% dan 100% gacha: proporsional hisoblash
                    $baseBonus = 10; // minimal bonus
                    $additional = $employee->bonus - $baseBonus;
                    $scale = ($percent - 80) / 20; // 80% dan 100% gacha masshtab
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

                $employee->increment('balance', $amount);

                Log::add(
                    $employee->user_id,
                    'KPI hisoblandi',
                    'salary_bonus',
                    null,
                    [
                        'payment_type' => $employee->payment_type,
                        'plan' => $planValue,
                        'bajarilgan' => $actualValue,
                        'foiz' => $percent,
                        'bonus_foiz' => round($bonusPercent, 2),
                        'summasi' => $amount,
                        'oy' => $month,
                        'yil' => $year,
                    ]
                );
            }
        }

        $this->info("KPI hisoblandi va bonuslar qo'shildi: {$month}/{$year}");
        $this->info("Umumiy plan: {$totalPlan}, Bajarilgan: {$totalActual}, Foiz: {$overallPercent}%");
    }
}