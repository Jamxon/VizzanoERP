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

    protected $botToken = '8443951014:AAHMmbRm5bgFCRk1h4GjFP5WUg9H1rMsiIk';
    protected $chatId = '5228018221';

    public function handle()
    {
        $logFile = storage_path('logs/requests.log');

        if (!File::exists($logFile)) {
            $this->error("❌ Log fayl topilmadi!");
            return;
        }

        // 1️⃣ Log fayldan so'nggi 1 soatlik ma'lumotlarni o'qish
        $lines = $this->readRecentLogs($logFile);

        if ($lines->isEmpty()) {
            $this->info("⚠️ So'nggi 1 soatda so'rovlar yo'q.");
            return;
        }

        // 2️⃣ Statistika hisoblash
        $total = $lines->count();
        $deviceCount = $lines->filter(fn($x) => str_contains($x['path'] ?? '', 'hikvision/event'))->count();
        $userCount = $total - $deviceCount;

        $userActivity = $lines->whereNotNull('user_id')
            ->groupBy('user_id')
            ->map->count()
            ->sortDesc();

        $mostActive = $userActivity->take(5);
        $leastActive = $userActivity->reverse()->take(5);

        // Foydalanuvchi ma'lumotlarini olish
        $users = User::with('employee:id,user_id,name')
            ->whereIn('id', $userActivity->keys())
            ->get()
            ->keyBy('id');

        $topEndpoints = $lines->where('path', '!=', 'Nomalum endpoint')
            ->groupBy('path')->map->count()->sortDesc()->take(5);
        
        $fastest = $lines->where('path', '!=', 'Nomalum endpoint')
            ->sortBy('duration_ms')->take(5);
        
        $slowest = $lines->where('path', '!=', 'Nomalum endpoint')
            ->sortByDesc('duration_ms')->take(5);
        
        $errors = $lines->where('status', '>=', 400)
            ->where('path', '!=', 'Nomalum endpoint')
            ->groupBy('path')->map->count()->sortDesc()->take(5);

        // 3️⃣ Server yuklanishi
        $usage = $this->getSystemUsage();

        // 4️⃣ Xabarlarni tayyorlash (5 ta alohida xabar)
        $messages = [
            // Xabar 1: Server holati va asosiy statistika
            "📊 VizzanoERP Monitoring Report\n"
            . "🕒 " . now()->toDateTimeString() . "\n\n"
            . "{$usage['cpu']['status']} CPU: {$usage['cpu']['percent']}%\n"
            . "{$usage['ram']['status']} RAM: {$usage['ram']['used']} / {$usage['ram']['total']} ({$usage['ram']['percent']}%)\n"
            . "{$usage['disk']['status']} Disk: {$usage['disk']['used']} / {$usage['disk']['total']} ({$usage['disk']['percent']}%)\n\n"
            . $this->getServerStatusText(max($usage['cpu']['percent'], $usage['ram']['percent'], $usage['disk']['percent']))
            . "\n\n📈 So'rov statistikasi (So'nggi 1 soat)\n"
            . "🔹 Jami: {$total} ta\n"
            . "🤖 Qurilmadan: {$deviceCount} ta\n"
            . "👨‍💻 Foydalanuvchilardan: {$userCount} ta",

            // Xabar 2: Eng ko'p urilgan endpointlar
            "🔝 Eng ko'p urilgan endpointlar:\n"
            . $this->formatList($topEndpoints),

            // Xabar 3: Tez va sekin endpointlar
            "⚡ Eng tez ishlagan 5 ta endpoint:\n"
            . $this->formatSpeedList($fastest, true)
            . "\n\n🐢 Eng sekin ishlagan 5 ta endpoint:\n"
            . $this->formatSpeedList($slowest),

            // Xabar 4: Xatolar
            "❌ Xato bergan endpointlar:\n"
            . $this->formatList($errors),

            // Xabar 5: Foydalanuvchilar
            "🟢 Eng faol foydalanuvchilar:\n"
            . $this->formatUserList($mostActive, $users)
            . "\n\n🔴 Eng sust foydalanuvchilar:\n"
            . $this->formatUserList($leastActive, $users)
            . "\n\n🛰 Monitoring by VizzanoERP Bot"
        ];

        // 5️⃣ Telegramga yuborish
        foreach ($messages as $message) {
            $this->sendMessage($message);
            sleep(1); // Flood limitdan qochish
        }

        $this->info("✅ Hisobot yuborildi!");

        // 6️⃣ Eski loglarni tozalash
        $this->cleanOldLogs($logFile);
    }

    // 🔸 So'nggi 1 soatlik loglarni o'qish
    private function readRecentLogs($logFile)
    {
        $lines = [];
        $handle = fopen($logFile, 'r');
        
        if (!$handle) {
            return collect([]);
        }

        while (($line = fgets($handle)) !== false) {
            $jsonStart = strpos($line, '{');
            if ($jsonStart === false) continue;

            $data = json_decode(substr($line, $jsonStart), true);
            if (!$data || !isset($data['time'])) continue;

            // Faqat so'nggi 1 soatlik ma'lumotlar
            if (Carbon::parse($data['time'])->greaterThan(Carbon::now()->subHour())) {
                $data['path'] = $data['path'] ?? 'Nomalum endpoint';
                if ($data['path'] === '/' || $data['path'] === '') {
                    $data['path'] = 'Nomalum endpoint';
                }
                $lines[] = $data;
            }
        }
        fclose($handle);

        return collect($lines);
    }

    // 🔸 CPU, RAM va Disk ma'lumotlarini olish
    private function getSystemUsage()
    {
        // CPU
        $cpuLoad = sys_getloadavg();
        $cpuCores = (int)shell_exec('nproc') ?: 1;
        $cpuPercent = isset($cpuLoad[0]) ? round($cpuLoad[0] * 100 / $cpuCores, 2) : 0;

        // RAM
        $memInfo = @file_get_contents('/proc/meminfo');
        if ($memInfo) {
            preg_match('/MemTotal:\s+(\d+)/', $memInfo, $totalMem);
            preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $freeMem);

            $totalMemKB = (int)($totalMem[1] ?? 0);
            $freeMemKB = (int)($freeMem[1] ?? 0);
            $usedMemKB = $totalMemKB - $freeMemKB;

            $ramPercent = $totalMemKB > 0 ? round(($usedMemKB / $totalMemKB) * 100, 2) : 0;
        } else {
            $totalMemKB = $usedMemKB = $ramPercent = 0;
        }

        // Disk
        $totalDisk = @disk_total_space("/") ?: 1;
        $freeDisk = @disk_free_space("/") ?: 0;
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

    // 🔸 Server holati matni
    private function getServerStatusText(float $percent): string
    {
        return match (true) {
            $percent < 50 => "😎 *Server tinch, hammasi joyida!*",
            $percent < 75 => "🙂 *Server bosim ostida, ammo barqaror.*",
            $percent < 90 => "⚠️ *Server yuklama ortmoqda, optimallashtirish tavsiya etiladi!*",
            default => "🚨 *Server haddan tashqari band, zudlik bilan tekshirish kerak!*",
        };
    }

    // 🔸 Byte formatlaash
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    // 🔸 Yuklanish emoji
    private function getLoadEmoji($percent)
    {
        return match (true) {
            $percent < 50 => "🟢",
            $percent < 75 => "🟡",
            $percent < 90 => "🟠",
            default => "🔴"
        };
    }

    // 🔸 Ro'yxat formatlash
    private function formatList($collection)
    {
        return $collection->isEmpty()
            ? "_Hech narsa topilmadi_"
            : $collection->map(fn($count, $path) => "• {$path} — {$count} ta")->join("\n");
    }

    // 🔸 Tezlik ro'yxatini formatlash
    private function formatSpeedList($logs, $fastest = false)
    {
        return $logs->isEmpty()
            ? "_Hech narsa topilmadi_"
            : $logs->map(fn($log) => "• {$log['path']} — {$log['duration_ms']} ms")->join("\n");
    }

    // 🔸 Foydalanuvchilar ro'yxatini formatlash
    private function formatUserList($collection, $users)
    {
        if ($collection->isEmpty()) {
            return "_Topilmadi_";
        }

        return $collection->map(function ($count, $userId) use ($users) {
            $user = $users[$userId] ?? null;

            if ($user && $user->employee) {
                $name = $user->employee->name;
                return "• {$name} — {$count} ta";
            } elseif ($user) {
                return "• User #{$user->id} — {$count} ta";
            } else {
                return "• Noma'lum — {$count} ta";
            }
        })->join("\n");
    }

    // 🔸 Eski loglarni tozalash (1 kundan eski)
    private function cleanOldLogs($logFile)
    {
        try {
            $lines = File::lines($logFile);
            $filtered = collect($lines)
                ->filter(function ($line) {
                    $jsonStart = strpos($line, '{');
                    if ($jsonStart === false) return false;

                    $data = json_decode(substr($line, $jsonStart), true);
                    if (!isset($data['time'])) return false;

                    return Carbon::parse($data['time'])->greaterThan(Carbon::now()->subDay());
                })
                ->values()
                ->all();

            File::put($logFile, implode("\n", $filtered));
            $this->info("🧹 Eski loglar tozalandi.");
        } catch (\Throwable $e) {
            $this->error("❌ Log tozalashda xatolik: " . $e->getMessage());
        }
    }

    // 🔸 Telegramga xabar yuborish
    private function sendMessage(string $text)
    {
        try {
            // Markdown maxsus belgilarini escape qilish
            $text = $this->escapeMarkdown($text);
            
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => 'MarkdownV2',
            ]);

            if (!$response->successful()) {
                $this->error("❌ Telegram xatolik: " . $response->body());
            }
        } catch (\Throwable $e) {
            $this->error("❌ Yuborishda xatolik: " . $e->getMessage());
        }
    }

    // 🔸 Markdown V2 uchun maxsus belgilarni escape qilish
    private function escapeMarkdown(string $text): string
    {
        // MarkdownV2 da escape qilish kerak bo'lgan belgilar
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        
        // Har bir maxsus belgini \ bilan escape qilamiz
        foreach ($specialChars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        
        return $text;
    }
}