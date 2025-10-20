<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class MonitoringReport extends Command
{
    protected $signature = 'monitoring:report';
    protected $description = 'Server va foydalanuvchi faoliyati haqida jonli hisobot';

    public function handle()
    {
        $botToken = '8443951014:AAHMmbRm5bgFCRk1h4GjFP5WUg9H1rMsiIk';
        $chatId = '5228018221';
        $logFile = storage_path('logs/requests.log');

        if (!File::exists($logFile)) {
            $this->error("❌ Log fayl topilmadi!");
            return;
        }

        $lines = collect(File::lines($logFile))
            ->map(fn($line) => json_decode(substr($line, strpos($line, '{')), true))
            ->filter(fn($data) => isset($data['time']) && Carbon::parse($data['time'])->greaterThan(Carbon::now()->subHour()))
            ->map(function ($data) {
                // agar path yo‘q bo‘lsa, uni 'unknown' deb belgilaymiz
                $data['path'] = $data['path'] ?? 'Noma’lum endpoint';
                if ($data['path'] === '/' || $data['path'] === '') {
                    $data['path'] = 'Noma’lum endpoint';
                }
                return $data;
            });

        if ($lines->isEmpty()) {
            $this->info("⚠️ So‘nggi 1 soatda so‘rovlar yo‘q.");
            return;
        }

        $total = $lines->count();
        $deviceCount = $lines->filter(fn($x) => str_contains($x['path'] ?? '', 'hikvision/event'))->count();
        $userCount = $total - $deviceCount;

        $userActivity = $lines->whereNotNull('user_id')
            ->groupBy('user_id')
            ->map->count()
            ->sortDesc();

        $mostActive = $userActivity->take(5);
        $leastActive = $userActivity->reverse()->take(5);

        // Foydalanuvchi ma’lumotlarini olish
        $users = User::with('employee:id,user_id,name,position')
            ->whereIn('id', $userActivity->keys())
            ->get()
            ->keyBy('id');

        $topEndpoints = $lines->where('path', '!=', 'Noma’lum endpoint')
            ->groupBy('path')->map->count()->sortDesc()->take(5);
        $fastest = $lines->where('path', '!=', 'Noma’lum endpoint')
            ->sortBy('duration_ms')->take(5);
        $slowest = $lines->where('path', '!=', 'Noma’lum endpoint')
            ->sortByDesc('duration_ms')->take(5);
        $errors = $lines->where('status', '>=', 400)
            ->where('path', '!=', 'Noma’lum endpoint')
            ->groupBy('path')->map->count()->sortDesc()->take(5);

        // 🔹 Server yuklanishi (to‘g‘ri hisoblash)
        $usage = $this->getSystemUsage();

        $message = "😎 *Server tinch, hammasi joyida!*\n"
            . "🧠 *Server Monitoring (So‘nggi 1 soat)*\n"
            . "🕒 " . now()->toDateTimeString() . "\n\n"
            . "{$usage['cpu']['status']} CPU: {$usage['cpu']['percent']}%\n"
            . "{$usage['ram']['status']} RAM: {$usage['ram']['used']} / {$usage['ram']['total']} ({$usage['ram']['percent']}%)\n"
            . "{$usage['disk']['status']} Disk: {$usage['disk']['used']} / {$usage['disk']['total']} ({$usage['disk']['percent']}%)\n\n"
            . "📈 *So‘rov statistikasi*\n"
            . "🔹 Jami: {$total} ta\n"
            . "🤖 Qurilmadan: {$deviceCount} ta\n"
            . "👨‍💻 Foydalanuvchilardan: {$userCount} ta\n\n"
            . "🔝 *Eng ko‘p urilgan endpointlar:*\n" . $this->formatList($topEndpoints)
            . "\n⚡ *Eng tez endpointlar:*\n" . $this->formatSpeedList($fastest, true)
            . "\n🐢 *Eng sekin endpointlar:*\n" . $this->formatSpeedList($slowest)
            . "\n⚠️ *Xato bergan endpointlar:*\n" . $this->formatList($errors)
            . "\n\n🧍‍♂️ *Eng faol foydalanuvchilar:*\n" . $this->formatUserList($mostActive, $users)
            . "\n😴 *Eng sust foydalanuvchilar:*\n" . $this->formatUserList($leastActive, $users)
            . "\n\n🎯 Monitoring by *VizzanoERP Bot*";

        // Telegramga yuborish
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ]);

        $this->info("✅ Hisobot yuborildi!");

        // 🔹 7. Eski loglarni avtomatik tozalash
        $this->cleanOldLogs($logFile);
    }

    // 🔹 CPU, RAM va Diskni aniq hisoblovchi funksiya
    private function getSystemUsage()
    {
        // CPU
        $cpuLoad = sys_getloadavg();
        $cpuCores = (int)shell_exec('nproc');
        $cpuPercent = isset($cpuLoad[0]) ? round($cpuLoad[0] * 100 / $cpuCores, 2) : 0;

        // RAM
        $memInfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $memInfo, $totalMem);
        preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $freeMem);

        $totalMemKB = (int)$totalMem[1];
        $freeMemKB = (int)$freeMem[1];
        $usedMemKB = $totalMemKB - $freeMemKB;

        $ramPercent = round(($usedMemKB / $totalMemKB) * 100, 2);

        // Disk
        $totalDisk = disk_total_space("/");
        $freeDisk = disk_free_space("/");
        $usedDisk = $totalDisk - $freeDisk;
        $diskPercent = round(($usedDisk / $totalDisk) * 100, 2);

        return [
            'cpu' => [
                'percent' => $cpuPercent,
                'status' => $this->getLoadEmoji($cpuPercent),
            ],
            'ram' => [
                'percent' => $ramPercent,
                'used' => $this->formatBytes($usedMemKB * 1024),
                'total' => $this->formatBytes($totalMemKB * 1024),
                'status' => $this->getLoadEmoji($ramPercent),
            ],
            'disk' => [
                'percent' => $diskPercent,
                'used' => $this->formatBytes($usedDisk),
                'total' => $this->formatBytes($totalDisk),
                'status' => $this->getLoadEmoji($diskPercent),
            ],
        ];
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function getLoadEmoji($percent)
    {
        return match (true) {
            $percent < 50 => "🟢",
            $percent < 75 => "🟡",
            $percent < 90 => "🟠",
            default => "🔴"
        };
    }

    private function formatList($collection)
    {
        return $collection->isEmpty()
            ? "_Hech narsa topilmadi_\n"
            : $collection->map(fn($count, $path) => "• {$path} — {$count} ta")->join("\n");
    }

    private function formatSpeedList($logs, $fastest = false)
    {
        $emoji = $fastest ? "⚡" : "🐢";
        return $logs->isEmpty()
            ? "_Hech narsa topilmadi_\n"
            : $logs->map(fn($log) => "{$emoji} {$log['path']} — {$log['duration_ms']} ms")->join("\n");
    }

    private function cleanOldLogs($logFile)
    {
        $lines = File::lines($logFile);
        $filtered = collect($lines)
            ->filter(function ($line) {
                $jsonStart = strpos($line, '{');
                if ($jsonStart === false) return false;

                $data = json_decode(substr($line, $jsonStart), true);
                if (!isset($data['time'])) return false;

                $time = Carbon::parse($data['time']);
                return $time->greaterThan(Carbon::now()->subDay());
            })
            ->values()
            ->all();

        File::put($logFile, implode("\n", $filtered));
    }

    private function formatUserList($collection, $users)
    {
        if ($collection->isEmpty()) {
            return "_Topilmadi_\n";
        }

        return $collection->map(function ($count, $userId) use ($users) {
            $user = $users[$userId] ?? null;

            if ($user && $user->employee) {
                return "• {$user->employee->name} ({$user->employee->position}) — {$count} ta so‘rov";
            } elseif ($user) {
                return "• User #{$user->id} — {$count} ta so‘rov";
            } else {
                return "• Noma’lum foydalanuvchi — {$count} ta so‘rov";
            }
        })->join("\n");
    }

}
