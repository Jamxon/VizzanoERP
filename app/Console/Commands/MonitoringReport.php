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
            ->filter(fn($data) => isset($data['time']) && Carbon::parse($data['time'])->greaterThan(Carbon::now()->subHour()));

        if ($lines->isEmpty()) {
            $this->info("⚠️ So‘nggi 1 soatda so‘rovlar yo‘q.");
            return;
        }

        $total = $lines->count();
        $deviceCount = $lines->filter(fn($x) => str_contains($x['path'] ?? '', 'hikvision/event'))->count();
        $userCount = $total - $deviceCount;

        $topEndpoints = $lines->groupBy('path')->map->count()->sortDesc()->take(5);
        $fastest = $lines->sortBy('duration_ms')->take(5);
        $slowest = $lines->sortByDesc('duration_ms')->take(5);
        $errors = $lines->where('status', '>=', 400)->groupBy('path')->map->count()->sortDesc()->take(5);

        // 🔹 Server load
        $cpu = (float) trim(shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'"));
        $ramUsed = shell_exec("free -m | awk 'NR==2{print $3}'");
        $ramTotal = shell_exec("free -m | awk 'NR==2{print $2}'");
        $ramPercent = round(($ramUsed / $ramTotal) * 100, 2);
        $diskInfo = trim(shell_exec("df -h / | awk 'NR==2{print $3\"/\"$2\" (\"$5\")\"}'"));

        $cpuEmoji = $this->getLoadEmoji($cpu);
        $ramEmoji = $this->getLoadEmoji($ramPercent);

        $message = "😎 *Server tinch, hammasi joyida!*\n"
            . "🧠 *Server Monitoring (So‘nggi 1 soat)*\n"
            . "🕒 " . now()->toDateTimeString() . "\n\n"
            . "{$cpuEmoji} CPU: {$cpu}%\n"
            . "{$ramEmoji} RAM: {$ramUsed}/{$ramTotal}MB ({$ramPercent}%)\n"
            . "🟢 Disk: {$diskInfo}\n\n"
            . "📈 *So‘rov statistikasi*\n"
            . "🔹 Jami: {$total} ta\n"
            . "🤖 Qurilmadan: {$deviceCount} ta\n"
            . "👨‍💻 Foydalanuvchilardan: {$userCount} ta\n\n"
            . "🔝 *Eng ko‘p urilgan endpointlar:*\n" . $this->formatList($topEndpoints)
            . "\n⚡ *Eng tez endpointlar:*\n" . $this->formatSpeedList($fastest, true)
            . "\n🐢 *Eng sekin endpointlar:*\n" . $this->formatSpeedList($slowest)
            . "\n⚠️ *Xato bergan endpointlar:*\n" . $this->formatList($errors)
            . "\n\n🎯 Monitoring by *VizzanoERP Bot*";

        // Telegramga yuborish
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ]);

        $this->info("✅ Hisobot yuborildi!");
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
}
