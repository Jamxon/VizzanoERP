<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MonitoringReport extends Command
{
    protected $signature = 'monitoring:report';
    protected $description = 'Send system and API monitoring report to Telegram bot';

    public function handle()
    {
        try {
            $telegramToken = '8443951014:AAHMmbRm5bgFCRk1h4GjFP5WUg9H1rMsiIk';
            $chatId = '5228018221';

            if (!$telegramToken || !$chatId) {
                $this->error('Telegram token or chat_id not set in config.');
                return;
            }

            $this->info('Monitoring started...');

            // === 1. SYSTEM USAGE ===
            $cpuUsage = $this->getCpuUsage();
            $ramUsage = $this->getRamUsage();

            $systemMessage = "🖥 *SYSTEM MONITORING (Vizzano ERP)*\n\n" .
                "CPU Usage: *{$cpuUsage}%*\n" .
                "RAM Usage: *{$ramUsage}%*\n" .
                "Date: " . now()->format('Y-m-d H:i:s');

            $this->sendTelegramMessage($telegramToken, $chatId, $systemMessage);

            // === 2. USER COUNT ===
            $usersCount = DB::table('users')->count();
            $employeesCount = DB::table('employees')->count();
            $branchesCount = DB::table('branches')->count();

            $usersMessage = "👥 *Foydalanuvchilar statistikasi*\n\n" .
                "Umumiy userlar soni: *{$usersCount}*\n" .
                "Xodimlar soni: *{$employeesCount}*\n" .
                "Filiallar soni: *{$branchesCount}*";

            $this->sendTelegramMessage($telegramToken, $chatId, $usersMessage);

            // === 3. API STATISTICS ===
            $endpointStats = $this->getEndpointStatistics();

            // Eng ko‘p va eng kam urilgan endpointlar
            $mostUsed = $endpointStats['most_used'];
            $leastUsed = $endpointStats['least_used'];

            $endpointMessage1 = "📊 *Endpoint statistika*\n\n" .
                "🔝 Eng ko‘p ishlatilgan endpointlar:\n" .
                $this->formatList($mostUsed) . "\n\n" .
                "🔻 Eng kam ishlatilgan endpointlar:\n" .
                $this->formatList($leastUsed);

            $this->sendTelegramMessage($telegramToken, $chatId, $endpointMessage1);

            // Eng tez, eng sekin, va xato bergan endpointlar
            $fastest = $endpointStats['fastest'];
            $slowest = $endpointStats['slowest'];
            $errorEndpoints = $endpointStats['error_endpoints'];

            $endpointMessage2 = "⚙️ *Endpoint performance*\n\n" .
                "⚡️ Eng tez (5 ta):\n" . $this->formatList($fastest, 'avg_duration', 'ms') . "\n\n" .
                "🐢 Eng sekin (5 ta):\n" . $this->formatList($slowest, 'avg_duration', 'ms') . "\n\n" .
                "❌ Xato bergan endpointlar:\n" . $this->formatList($errorEndpoints, 'error_count', 'xato');

            $this->sendTelegramMessage($telegramToken, $chatId, $endpointMessage2);

            // === 4. USER ACTIVITY ===
            $activity = $this->getUserActivity();
            $activeUsers = $activity['active'];
            $inactiveUsers = $activity['inactive'];

            $usersMessage2 = "👤 *Foydalanuvchilar faolligi*\n\n" .
                "🔥 Eng faol foydalanuvchilar:\n" . $this->formatList($activeUsers, 'request_count', 'ta') . "\n\n" .
                "😴 Eng sust foydalanuvchilar:\n" . $this->formatList($inactiveUsers, 'request_count', 'ta');

            $this->sendTelegramMessage($telegramToken, $chatId, $usersMessage2);

            // === 5. FOOTER ===
            $footer = "✅ *Monitoring by Vizzano ERP Bot*";
            $this->sendTelegramMessage($telegramToken, $chatId, $footer);

            $this->info('Monitoring report sent successfully.');

        } catch (\Throwable $e) {
            Log::error("Monitoring report error: " . $e->getMessage());
            $this->error($e->getMessage());
        }
    }

    private function getCpuUsage()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cpuLoad = shell_exec('wmic cpu get loadpercentage /value');
            preg_match('/LoadPercentage=(\d+)/', $cpuLoad, $matches);
            return $matches[1] ?? 0;
        } else {
            $load = sys_getloadavg();
            $cpuCores = (int) trim(shell_exec('nproc'));
            $usage = ($load[0] / $cpuCores) * 100;
            return round($usage, 2);
        }
    }

    private function getRamUsage()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $free = shell_exec('wmic OS get FreePhysicalMemory /Value');
            $total = shell_exec('wmic ComputerSystem get TotalPhysicalMemory /Value');
            preg_match('/FreePhysicalMemory=(\d+)/', $free, $freeMatches);
            preg_match('/TotalPhysicalMemory=(\d+)/', $total, $totalMatches);
            if (!empty($freeMatches) && !empty($totalMatches)) {
                $freeMem = (float)$freeMatches[1] * 1024;
                $totalMem = (float)$totalMatches[1];
                return round((1 - $freeMem / $totalMem) * 100, 2);
            }
            return 0;
        } else {
            $free = (int) shell_exec("free -m | awk '/Mem:/ {print $4+$6+$7}'");
            $total = (int) shell_exec("free -m | awk '/Mem:/ {print $2}'");
            return $total > 0 ? round((1 - $free / $total) * 100, 2) : 0;
        }
    }

    private function getEndpointStatistics()
    {
        $logs = DB::table('request_logs')
            ->select('endpoint', DB::raw('COUNT(*) as request_count, AVG(duration) as avg_duration, SUM(is_error)::int as error_count'))
            ->groupBy('endpoint')
            ->get();

        return [
            'most_used' => $logs->sortByDesc('request_count')->take(5)->values(),
            'least_used' => $logs->sortBy('request_count')->take(5)->values(),
            'fastest' => $logs->sortBy('avg_duration')->take(5)->values(),
            'slowest' => $logs->sortByDesc('avg_duration')->take(5)->values(),
            'error_endpoints' => $logs->sortByDesc('error_count')->take(5)->values(),
        ];
    }

    private function getUserActivity()
    {
        $userStats = DB::table('request_logs')
            ->select('user_id', DB::raw('COUNT(*) as request_count'))
            ->groupBy('user_id')
            ->get();

        $active = $userStats->sortByDesc('request_count')->take(5)->values();
        $inactive = $userStats->sortBy('request_count')->take(5)->values();

        return [
            'active' => $active,
            'inactive' => $inactive,
        ];
    }

    private function formatList($collection, $key = 'request_count', $suffix = 'ta')
    {
        if ($collection->isEmpty()) return "_Maʼlumot yoʻq_";

        return $collection->map(function ($item) use ($key, $suffix) {
            $name = $item->endpoint ?? ('User #' . ($item->user_id ?? '-'));
            $count = round($item->{$key}, 2);
            return "- {$name} — *{$count} {$suffix}*";
        })->implode("\n");
    }

    private function sendTelegramMessage($token, $chatId, $text)
    {
        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
        sleep(1); // Telegram rate-limit uchun
    }
}
