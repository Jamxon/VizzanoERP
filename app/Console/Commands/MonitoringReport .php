<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MonitoringReport extends Command
{
    protected $signature = 'monitoring:report';
    protected $description = 'Server va so‘rov statistikasi haqida batafsil Telegram hisobot';

    public function handle()
    {
        $botToken = '8443951014:AAHMmbRm5bgFCRk1h4GjFP5WUg9H1rMsiIk';
        $chatId = '5228018221';

        $cpu = trim(shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'"));
        $ram = trim(shell_exec("free -m | awk 'NR==2{printf \"%s/%sMB (%.2f%%)\", $3,$2,$3*100/$2 }'"));
        $disk = trim(shell_exec("df -h / | awk 'NR==2{printf \"%d/%dGB (%s)\", $3,$2,$5}'"));

        // 1 soatlik loglarni olish
        $logFile = storage_path('logs/requests.log');
        $logs = collect(file($logFile))
            ->map(fn($line) => json_decode(substr($line, strpos($line, '{')), true))
            ->filter(fn($log) => isset($log['time']) && now()->diffInHours($log['time']) < 1);

        $total = $logs->count();
        $topEndpoints = $logs->groupBy('path')->map->count()->sortDesc()->take(5);
        $slowest = $logs->sortByDesc('duration_ms')->take(3);
        $errors = $logs->where('status', '>=', 400)->groupBy('path')->map->count()->sortDesc()->take(3);

        $message = "🧠 *Server Monitoring (so‘nggi 1 soat)*\n"
            . "🕒 " . now()->toDateTimeString() . "\n\n"
            . "🔥 CPU: {$cpu}%\n💾 RAM: {$ram}\n📂 Disk: {$disk}\n\n"
            . "📈 *So‘rov statistikasi*\n"
            . "Jami so‘rovlar: {$total}\n\n"
            . "🔝 Eng ko‘p urilgan endpointlar:\n" . $this->formatList($topEndpoints)
            . "\n🐢 Eng sekin endpointlar:\n" . $this->formatSlowList($slowest)
            . "\n⚠️ Eng ko‘p xato bergan endpointlar:\n" . $this->formatList($errors);

        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    }

    private function formatList($collection)
    {
        if ($collection->isEmpty()) return "_Hech narsa topilmadi_\n";
        return $collection->map(fn($count, $path) => "• `$path` — {$count} ta")->join("\n");
    }

    private function formatSlowList($logs)
    {
        if ($logs->isEmpty()) return "_Hech narsa topilmadi_\n";
        return $logs->map(fn($log) => "• {$log['path']} — {$log['duration_ms']} ms")->join("\n");
    }
}
