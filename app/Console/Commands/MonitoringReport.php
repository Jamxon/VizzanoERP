<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MonitoringReport extends Command
{
    protected $signature = 'monitoring:report';
    protected $description = 'Server, foydalanuvchi va endpoint statistikasi haqida batafsil Telegram hisobot';

    public function handle()
    {
        $botToken = '8443951014:AAHMmbRm5bgFCRk1h4GjFP5WUg9H1rMsiIk';
        $chatId = '5228018221';

        // 🔹 Tizim resurslari
        $cpu = trim(shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'"));
        $ram = trim(shell_exec("free -m | awk 'NR==2{printf \"%s/%sMB (%.2f%%)\", $3,$2,$3*100/$2 }'"));
        $disk = trim(shell_exec("df -h / | awk 'NR==2{printf \"%d/%dGB (%s)\", $3,$2,$5}'"));

        // 🔹 Loglarni o‘qish (so‘nggi 1 soat)
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

        // 🔹 Eng faol va sust foydalanuvchilar
        $userActivity = $logs->groupBy('user_id')->map->count();
        $mostActive = $this->getUsersInfo($userActivity->sortDesc()->take(3));
        $leastActive = $this->getUsersInfo($userActivity->sort()->take(3));

        // 🔹 Xabarni tayyorlash
        $message = "🧠 *Server Monitoring (So‘nggi 1 soat)*\n"
            . "🕒 " . now()->toDateTimeString() . "\n\n"
            . "🔥 CPU: {$cpu}%\n💾 RAM: {$ram}\n📂 Disk: {$disk}\n\n"
            . "📈 *So‘rovlar statistikasi*\n"
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
            if (!$user) return "• [Unknown user] — {$count} so‘rov";

            $name = $user->employee->name ?? $user->name ?? 'Noma’lum';
            $position = $user->employee->position ?? '-';
            return "• {$name} ({$position}) — {$count} ta so‘rov";
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
