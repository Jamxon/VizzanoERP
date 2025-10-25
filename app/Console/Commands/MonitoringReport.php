<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\User;

class MonitoringReport extends Command
{
    protected $signature = 'monitoring:report';
    protected $description = 'Server va foydalanuvchi faoliyati haqida jonli hisobot';

    public function handle()
    {
        $botToken = '8443951014:AAHMmbRm5bgFCRk1h4GjFP5WUg9H1rMsiIk';
        $chatId = '5228018221';
        $logFile = storage_path('logs/requests.log');
        $hours = (int) $this->option('hours');

        if (!File::exists($logFile)) {
            $this->error("❌ Log fayl topilmadi!");
            return;
        }

        $lines = collect();
        foreach ($this->readLogFile($logFile) as $line) {
            $jsonStart = strpos($line, '{');
            if ($jsonStart === false) continue;

            $json = substr($line, $jsonStart);
            try {
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                continue;
            }

            if (!isset($data['time'])) continue;

            $time = Carbon::parse($data['time']);
            if ($time->greaterThan(now()->subHours($hours))) {
                $data['path'] = $data['path'] ?? 'Noma’lum endpoint';
                $lines->push($data);
            }
        }

        if ($lines->isEmpty()) {
            $this->info("⚠️ So‘nggi {$hours} soatda so‘rovlar yo‘q.");
            return;
        }

        // === STATISTIKA ===
        $total = $lines->count();
        $deviceCount = $lines->filter(fn($x) => str_contains($x['path'], 'hikvision/event'))->count();
        $userCount = $total - $deviceCount;

        $userActivity = $lines->whereNotNull('user_id')
            ->groupBy('user_id')->map->count()->sortDesc();

        $mostActive = $userActivity->take(5);
        $leastActive = $userActivity->reverse()->take(5);

        $users = User::with('employee:id,user_id,name,position')
            ->whereIn('id', $userActivity->keys())
            ->get()->keyBy('id');

        $topEndpoints = $lines->groupBy('path')->map->count()->sortDesc()->take(5);
        $fastest = $lines->where('path', '!=', 'Noma’lum endpoint')->sortBy('duration_ms')->take(5);
        $slowest = $lines->where('path', '!=', 'Noma’lum endpoint')->sortByDesc('duration_ms')->take(5);
        $errors = $lines->where('status', '>=', 400)->groupBy('path')->map->count()->sortDesc()->take(5);

        // === SERVER USAGE ===
        $usage = $this->getSystemUsage();

        // === Xabar bo‘limlari ===
        $messages = [];

        $messages[] = "😎 *Server Monitoring Report*\n🕒 " . now()->toDateTimeString() . "\n\n"
            . "{$usage['cpu']['status']} CPU: {$usage['cpu']['percent']}%\n"
            . "{$usage['ram']['status']} RAM: {$usage['ram']['used']} / {$usage['ram']['total']} ({$usage['ram']['percent']}%)\n"
            . "{$usage['disk']['status']} Disk: {$usage['disk']['used']} / {$usage['disk']['total']} ({$usage['disk']['percent']}%)\n\n"
            . "📊 *So‘rovlar statistikasi*\n"
            . "🔹 Jami: {$total} ta\n"
            . "🤖 Qurilmadan: {$deviceCount} ta\n"
            . "👨‍💻 Foydalanuvchilardan: {$userCount} ta\n"
            . "🧍‍♂️ Foydalanuvchilar soni: " . $userActivity->count();

        $messages[] = "🔝 *Eng ko‘p urilgan endpointlar:*\n" . $this->formatList($topEndpoints);

        $messages[] = "⚡ *Eng tez endpointlar:*\n" . $this->formatSpeedList($fastest, true)
            . "\n🐢 *Eng sekin endpointlar:*\n" . $this->formatSpeedList($slowest)
            . "\n⚠️ *Xato bergan endpointlar:*\n" . $this->formatList($errors);

        $messages[] = "🧍‍♂️ *Eng faol foydalanuvchilar:*\n" . $this->formatUserList($mostActive, $users)
            . "\n😴 *Eng sust foydalanuvchilar:*\n" . $this->formatUserList($leastActive, $users);

        $messages[] = "🎯 Monitoring by *VizzanoERP Bot*";

        // === Yuborish ===
        foreach ($messages as $msg) {
            $this->sendTelegram($botToken, $chatId, $msg);
            sleep(1); // Telegram flood-limitdan qochish
        }

        // === Ogohlantirish ===
        if ($usage['cpu']['percent'] > 90 || $usage['ram']['percent'] > 90 || $usage['disk']['percent'] > 90) {
            $alert = "🚨 *Server yuklanmoqda!*\nCPU: {$usage['cpu']['percent']}%\nRAM: {$usage['ram']['percent']}%\nDisk: {$usage['disk']['percent']}%";
            $this->sendTelegram($botToken, $chatId, $alert);
        }

        $this->cleanOldLogs($logFile);
        $this->info("✅ Hisobot yuborildi!");
    }

    // === System usage: to‘g‘ri hisoblash ===
    private function getSystemUsage()
    {
        // CPU: Linux uchun /proc/stat
        $stat1 = $this->readCpuStat();
        usleep(100000); // 0.1s
        $stat2 = $this->readCpuStat();

        $cpuPercent = $this->calculateCpuUsage($stat1, $stat2);

        // RAM
        $memInfo = file('/proc/meminfo', FILE_IGNORE_NEW_LINES);
        $mem = [];
        foreach ($memInfo as $line) {
            [$key, $val] = explode(':', $line);
            $mem[$key] = trim(str_replace('kB', '', $val));
        }
        $total = (int)$mem['MemTotal'];
        $free = (int)$mem['MemFree'] + (int)($mem['Buffers'] ?? 0) + (int)($mem['Cached'] ?? 0);
        $used = $total - $free;
        $ramPercent = round(($used / $total) * 100, 2);

        // Disk
        $totalDisk = disk_total_space("/");
        $freeDisk = disk_free_space("/");
        $usedDisk = $totalDisk - $freeDisk;
        $diskPercent = round(($usedDisk / $totalDisk) * 100, 2);

        return [
            'cpu' => ['percent' => $cpuPercent, 'status' => $this->getLoadEmoji($cpuPercent)],
            'ram' => [
                'percent' => $ramPercent,
                'used' => $this->formatBytes($used * 1024),
                'total' => $this->formatBytes($total * 1024),
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

    private function readCpuStat()
    {
        $data = file('/proc/stat');
        foreach ($data as $line) {
            if (strpos($line, 'cpu ') === 0) {
                $parts = preg_split('/\s+/', trim($line));
                return array_slice($parts, 1, 8);
            }
        }
        return [];
    }

    private function calculateCpuUsage($stat1, $stat2)
    {
        if (count($stat1) < 4 || count($stat2) < 4) return 0;

        $diff = array_map(fn($a, $b) => $b - $a, $stat1, $stat2);
        $total = array_sum($diff);
        $idle = $diff[3] ?? 0;

        return round((1 - $idle / $total) * 100, 2);
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes, 1024) : 0));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function getLoadEmoji($percent)
    {
        return match (true) {
            $percent < 50 => "🟢",
            $percent < 75 => "🟡",
            $percent < 90 => "🟠",
            default => "🔴",
        };
    }

    private function formatList($collection)
    {
        return $collection->isEmpty()
            ? "_Hech narsa topilmadi_"
            : $collection->map(fn($count, $path) => "• {$path} — {$count} ta")->join("\n");
    }

    private function formatSpeedList($logs, $fastest = false)
    {
        $emoji = $fastest ? "⚡" : "🐢";
        return $logs->isEmpty()
            ? "_Hech narsa topilmadi_"
            : $logs->map(fn($log) => "{$emoji} {$log['path']} — {$log['duration_ms']} ms")->join("\n");
    }

    private function formatUserList($collection, $users)
    {
        if ($collection->isEmpty()) {
            return "_Topilmadi_";
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

    private function readLogFile($path)
    {
        $handle = fopen($path, 'r');
        if (!$handle) return;
        while (($line = fgets($handle)) !== false) {
            yield trim($line);
        }
        fclose($handle);
    }

    private function sendTelegram($botToken, $chatId, $message)
    {
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    }

    private function cleanOldLogs($logFile)
    {
        $filtered = collect($this->readLogFile($logFile))
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
}
