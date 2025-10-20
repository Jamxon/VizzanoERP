<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MonitoringReport extends Command
{
    protected $signature = 'monitoring:report';
    protected $description = 'Server va so‘rov monitoring hisobotini chiqaradi';

    public function handle()
    {
        $now = Carbon::now();
        $oneHourAgo = $now->copy()->subHour();
        $oneDayAgo = $now->copy()->subDay();

        // 💾 Server resurslari
        $cpuUsage = shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'");
        $ram = shell_exec("free -m | awk 'NR==2{printf \"%s/%sMB (%.2f%%)\", \$3,\$2,\$3*100/\$2 }'");
        $disk = shell_exec("df -h / | awk 'NR==2{print \$3\"/\"\$2\" (\"\$5\")\"}'");

        // 📊 So‘rov statistikasi (1 soat)
        $totalRequests = Log::where('created_at', '>=', $oneHourAgo)->count();
        $deviceRequests = Log::where('created_at', '>=', $oneHourAgo)
            ->where(function ($q) {
                $q->where('user_agent', 'like', '%Hikvision%')
                  ->orWhere('path', 'like', '%device%')
                  ->orWhere('path', 'like', '%bridge%');
            })
            ->count();
        $userRequests = $totalRequests - $deviceRequests;

        // 🔝 Eng ko‘p urilgan endpointlar
        $topEndpoints = Log::selectRaw('path, COUNT(*) as total')
            ->where('created_at', '>=', $oneHourAgo)
            ->groupBy('path')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        // ⚡ Eng tez endpointlar
        $fastestEndpoints = Log::selectRaw('path, AVG(duration) as avg_time')
            ->where('created_at', '>=', $oneHourAgo)
            ->groupBy('path')
            ->orderBy('avg_time', 'asc')
            ->take(5)
            ->get();

        // 🐢 Eng sekin endpointlar
        $slowestEndpoints = Log::selectRaw('path, AVG(duration) as avg_time')
            ->where('created_at', '>=', $oneHourAgo)
            ->groupBy('path')
            ->orderByDesc('avg_time')
            ->take(5)
            ->get();

        // ⚠️ Eng ko‘p xato bergan endpointlar
        $errorEndpoints = Log::selectRaw('path, COUNT(*) as total')
            ->where('status', '>=', 400)
            ->where('created_at', '>=', $oneHourAgo)
            ->groupBy('path')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        // 👨‍💻 Eng faol foydalanuvchilar
        $activeUsers = Log::with(['user.employee'])
            ->selectRaw('user_id, COUNT(*) as total')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $oneHourAgo)
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        // 😴 Eng sust foydalanuvchilar (1 kun ichida eng kam urilgan)
        $inactiveUsers = Log::with(['user.employee'])
            ->selectRaw('user_id, COUNT(*) as total')
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $oneDayAgo)
            ->groupBy('user_id')
            ->orderBy('total', 'asc')
            ->take(5)
            ->get();

        // 🧠 Yakuniy hisobot
        $report = "😎 Server tinch, hammasi joyida!\n";
        $report .= "🧠 Server Monitoring (So‘nggi 1 soat)\n";
        $report .= "🕒 " . $now->format('Y-m-d H:i:s') . "\n\n";

        $report .= "🟢 CPU: " . trim($cpuUsage) . "%\n";
        $report .= "🟢 RAM: " . trim($ram) . "\n";
        $report .= "🟢 Disk: " . trim($disk) . "\n\n";

        $report .= "📈 So‘rov statistikasi\n";
        $report .= "🔹 Jami so‘rovlar: {$totalRequests} ta\n";
        $report .= "🤖 Qurilmadan kelganlar: {$deviceRequests} ta\n";
        $report .= "👨‍💻 Foydalanuvchilardan kelganlar: {$userRequests} ta\n\n";

        $report .= "🔝 Eng ko‘p urilgan endpointlar:\n";
        foreach ($topEndpoints as $e) {
            $report .= "• {$e->path} — {$e->total} ta\n";
        }

        $report .= "⚡ Eng tez endpointlar:\n";
        foreach ($fastestEndpoints as $e) {
            $report .= "⚡ {$e->path} — " . round($e->avg_time, 2) . " ms\n";
        }

        $report .= "🐢 Eng sekin endpointlar:\n";
        foreach ($slowestEndpoints as $e) {
            $report .= "🐢 {$e->path} — " . round($e->avg_time, 2) . " ms\n";
        }

        $report .= "⚠️ Eng ko‘p xato bergan endpointlar:\n";
        if ($errorEndpoints->isEmpty()) {
            $report .= "Hech narsa topilmadi\n";
        } else {
            foreach ($errorEndpoints as $e) {
                $report .= "• {$e->path} — {$e->total} ta\n";
            }
        }

        // 👨‍💻 Eng faol foydalanuvchilar
        $report .= "\n👨‍💻 Eng faol foydalanuvchilar:\n";
        if ($activeUsers->isEmpty()) {
            $report .= "Hech narsa topilmadi\n";
        } else {
            foreach ($activeUsers as $u) {
                $name = optional($u->user->employee)->fullname ?? $u->user->name ?? 'Noma’lum';
                $report .= "• {$name} — {$u->total} ta so‘rov\n";
            }
        }

        // 😴 Eng sust foydalanuvchilar
        $report .= "\n😴 Eng sust foydalanuvchilar:\n";
        if ($inactiveUsers->isEmpty()) {
            $report .= "Hech narsa topilmadi\n";
        } else {
            foreach ($inactiveUsers as $u) {
                $name = optional($u->user->employee)->fullname ?? $u->user->name ?? 'Noma’lum';
                $report .= "• {$name} — {$u->total} ta so‘rov\n";
            }
        }

        $report .= "\n\n🎯 Monitoring by VizzanoERP Bot\n";

        $this->info($report);
        \Log::channel('daily')->info($report);
    }
}
