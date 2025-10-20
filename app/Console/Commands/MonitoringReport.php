<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MonitoringReport extends Command
{
    protected $signature = 'monitoring:report';
    protected $description = 'Server va foydalanuvchi faoliyati haqida jonli Telegram hisobot';

    public function handle()
    {
        $botToken = '8443951014:AAHMmbRm5bgFCRk1h4GjFP5WUg9H1rMsiIk';
        $chatId = '5228018221';

        // 🔹 Server ma’lumotlari
        $cpu = (float) trim(shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'"));
        $ramUsed = shell_exec("free -m | awk 'NR==2{print $3}'");
        $ramTotal = shell_exec("free -m | awk 'NR==2{print $2}'");
        $ramPercent = round(($ramUsed / $ramTotal) * 100, 2);
        $diskInfo = shell_exec("df -h / | awk 'NR==2{print $3\"/\"$2\" (\"$5\")\"}'");
        $diskPercent = (int) trim(shell_exec("df / | awk 'NR==2 {print $5}' | tr -d '%'"));

        // 🔹 Emoji holatlari
        $cpuEmoji = $this->getLoadEmoji($cpu);
        $ramEmoji = $this->getLoadEmoji($ramPercent);
        $diskEmoji = $this->getLoadEmoji($diskPercent);

        // 🔹 Loglarni o‘qish
        $logFile = storage_path('logs/requests.log');
        if (!file_exists($logFile)) {
            $this->sendMessage($botToken, $chatId, "🚫 Log fayl topilmadi: `requests.log`");
            return;
        }

        $logs = collect(file($logFile))
            ->map(fn($line) => json_decode(substr($line, strpos($line, '{')), true))
            ->filter(fn($log) => isset($log['time']) && now()->diffInHours($log['time']) < 1);

        $total = $logs->count();
        $topEndpoints = $logs->groupBy('path')->map->count()->sortDesc()->take(5);
        $slowest = $logs->where('duration_ms', '>', 0)->sortByDesc('duration_ms')->take(3);
        $fastest = $logs->where('duration_ms', '>', 0)->sortBy('duration_ms')->take(3);
        $errors = $logs->where('status', '>=', 400)->groupBy('path')->map->count()->sortDesc()->take(3);

        $userActivity = $logs->groupBy('user_id')->map->count();
        $mostActive = $this->getUsersInfo($userActivity->sortDesc()->take(3));
        $leastActive = $this->getUsersInfo($userActivity->sort()->take(3));

        // 🔹 Stiker holat
        $sticker = $this->getStatusSticker($cpu, $ramPercent, $diskPercent);

        // 🔹 Xabarni tayyorlash
        $message = "{$sticker}\n"
            . "🧠 *Server Monitoring (So‘nggi 1 soat)*\n"
            . "🕒 " . now()->toDateTimeString() . "\n\n"
            . "{$cpuEmoji} CPU: {$cpu}%\n"
            . "{$ramEmoji} RAM: {$ramUsed}/{$ramTotal}MB ({$ramPercent}%)\n"
            . "{$diskEmoji} Disk: {$diskInfo}\n\n"
            . "📈 *So‘rov statistikasi*\n"
            . "Jami so‘rovlar: {$total}\n\n"
            . "🔝 Eng ko‘p urilgan endpointlar:\n" . $this->formatList($topEndpoints)
            . "\n⚡ Eng tez endpointlar:\n" . $this->formatSpeedList($fastest, true)
            . "\n🐢 Eng sekin endpointlar:\n" . $this->formatSpeedList($slowest)
            . "\n⚠️ Eng ko‘p xato bergan endpointlar:\n" . $this->formatList($errors)
            . "\n👨‍💻 *Eng faol foydalanuvchilar:*\n" . $mostActive
            . "\n😴 *Eng sust foydalanuvchilar:*\n" . $leastActive
            . "\n\n🎯 Monitoring by *VizzanoERP Bot*";

        $this->sendMessage($botToken, $chatId, $message);
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

    private function getStatusSticker($cpu, $ram, $disk)
    {
        $avg = ($cpu + $ram + $disk) / 3;
        return match (true) {
            $avg < 50 => "😎 Server tinch, hammasi joyida!",
            $avg < 75 => "🙂 Ozgina yuk bor, lekin nazorat ostida.",
            $avg < 90 => "😬 Yuklanish ortmoqda, ehtiyot bo‘ling!",
            default => "💀 Server zo‘riqmoqda! Tezda tekshirish kerak!"
        };
    }

    private function formatList($collection)
    {
        if ($collection->isEmpty()) return "_Hech narsa topilmadi_\n";
        return $collection->map(fn($count, $path) => "• `$path` — {$count} ta")->join("\n");
    }

    private function formatSpeedList($logs, $isFastest = false)
    {
        if ($logs->isEmpty()) return "_Hech narsa topilmadi_\n";
        $emoji = $isFastest ? "⚡" : "🐢";
        return $logs->map(fn($log) => "{$emoji} {$log['path']} — {$log['duration_ms']} ms")->join("\n");
    }

    private function getUsersInfo($userActivity)
    {
        if ($userActivity->isEmpty()) return "_Hech narsa topilmadi_\n";

        return $userActivity->map(function ($count, $userId) {
            $user = User::with('employee')->find($userId);
            if (!$user) return "• [Unknown] — {$count} so‘rov";
            $name = $user->employee->name ?? $user->name ?? 'Noma’lum';
            $pos = $user->employee->position ?? '-';
            return "• {$name} ({$pos}) — {$count} ta";
        })->join("\n");
    }

    private function sendMessage($botToken, $chatId, $text)
    {
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }
}
