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
        ini_set('memory_limit', '2048M');
        $logFile = storage_path('logs/requests.log');

        if (!File::exists($logFile)) {
            $this->error("❌ Log fayl topilmadi!");
            return;
        }

        $lines = $this->readRecentLogs($logFile);
        if ($lines->isEmpty()) {
            $this->info("⚠️ So'nggi 1 soatda so'rovlar yo'q.");
            return;
        }

        $total = $lines->count();
        $deviceCount = $lines->filter(fn($x) => str_contains($x['path'] ?? '', 'hikvision/event'))->count();
        $userCount = $total - $deviceCount;

        $userActivity = $lines->whereNotNull('user_id')
            ->groupBy('user_id')->map->count()->sortDesc();

        $mostActive = $userActivity->take(5);
        $leastActive = $userActivity->reverse()->take(5);

        $users = User::with('employee:id,user_id,name')
            ->whereIn('id', $userActivity->keys())
            ->get()->keyBy('id');

        $topEndpoints = $lines->groupBy('path')->map->count()->sortDesc()->take(5);
        $fastest = $lines->sortBy('duration_ms')->take(5);
        $slowest = $lines->sortByDesc('duration_ms')->take(5);
        $errors = $lines->where('status', '>=', 400)->groupBy('path')->map->count()->sortDesc()->take(5);

        $usage = $this->getSystemUsage();

        $messages = [
            $this->getServerStatusText(max(
                $usage['cpu']['percent'],
                $usage['ram']['percent'],
                $usage['disk']['percent']
            )) . "\n"
            . "📊 *VizzanoERP Monitoring Report*\n"
            . "🕒 " . now()->format('Y-m-d H:i:s') . "\n\n"
            . "{$usage['cpu']['status']} CPU: *{$usage['cpu']['percent']}%*\n"
            . "{$usage['ram']['status']} RAM: *{$usage['ram']['used']} / {$usage['ram']['total']}* ({$usage['ram']['percent']}%)\n"
            . "{$usage['disk']['status']} Disk: *{$usage['disk']['used']} / {$usage['disk']['total']}* ({$usage['disk']['percent']}%)\n\n"
            . "📈 So'rovlar (1 soat ichida)\n"
            . "🔹 Jami: *{$total}*\n"
            . "🤖 Qurilmadan: *{$deviceCount}*\n"
            . "👨‍💻 Foydalanuvchilardan: *{$userCount}*",

            "🔝 *Eng ko‘p ishlatilgan endpointlar:*\n" . $this->formatList($topEndpoints),

            "⚡ *Eng tez endpointlar:*\n" . $this->formatSpeedList($fastest)
            . "\n\n🐢 *Eng sekin endpointlar:*\n" . $this->formatSpeedList($slowest),

            "❌ *Xato bergan endpointlar:*\n" . $this->formatList($errors),

            "🟢 *Eng faol foydalanuvchilar:*\n" . $this->formatUserList($mostActive, $users)
            . "\n\n🔴 *Eng sust foydalanuvchilar:*\n" . $this->formatUserList($leastActive, $users)
            . "\n\n🛰 Monitoring by *VizzanoERP Bot*"
        ];

        foreach ($messages as $msg) {
            $this->sendMessage($msg);
            sleep(1);
        }

        $this->info("✅ Hisobot yuborildi!");
        $this->cleanOldLogs($logFile);
    }

    private function readRecentLogs($file)
    {
        if (!is_readable($file)) return collect();

        $lines = [];
        foreach (file($file) as $line) {
            $pos = strpos($line, '{');
            if ($pos === false) continue;

            $data = json_decode(substr($line, $pos), true);
            if (!$data || empty($data['time'])) continue;

            if (Carbon::parse($data['time'])->greaterThan(Carbon::now()->subHour())) {
                $data['path'] = $data['path'] ?? 'Unknown';
                $lines[] = $data;
            }
        }

        return collect($lines)->take(5000);
    }

    private function getSystemUsage()
    {
        $cpuLoad = sys_getloadavg();
        $cpuCores = (int)shell_exec('nproc 2>/dev/null') ?: 4;
        $cpuPercent = round(($cpuLoad[0] / $cpuCores) * 100, 2);

        $mem = @file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $mem, $t);
        preg_match('/MemAvailable:\s+(\d+)/', $mem, $a);
        $total = (int)($t[1] ?? 0);
        $avail = (int)($a[1] ?? 0);
        $used = $total - $avail;
        $ramPercent = round(($used / $total) * 100, 2);

        $diskTotal = @disk_total_space("/") ?: 1;
        $diskFree = @disk_free_space("/") ?: 0;
        $diskUsed = $diskTotal - $diskFree;
        $diskPercent = round(($diskUsed / $diskTotal) * 100, 2);

        return [
            'cpu' => [ 'percent' => $cpuPercent, 'status' => $this->getLoadEmoji($cpuPercent) ],
            'ram' => [ 'percent' => $ramPercent, 'used' => $this->formatBytes($used*1024), 'total' => $this->formatBytes($total*1024), 'status' => $this->getLoadEmoji($ramPercent) ],
            'disk' => [ 'percent' => $diskPercent, 'used' => $this->formatBytes($diskUsed), 'total' => $this->formatBytes($diskTotal), 'status' => $this->getLoadEmoji($diskPercent) ],
        ];
    }

    private function getLoadEmoji($p)
    {
        return match (true) {
            $p < 50 => "🟢",
            $p < 75 => "🟡",
            $p < 90 => "🟠",
            default => "🔴",
        };
    }

    private function getServerStatusText($p)
    {
        return match (true) {
            $p < 50 => "😎 *Server tinch, hammasi joyida!*",
            $p < 75 => "🙂 *Server biroz yuk ostida*",
            $p < 90 => "⚠️ *Server yuklanmoqda!*",
            default => "🚨 *SOS — Server haddan tashqari band!*",
        };
    }

    private function formatBytes($bytes)
    {
        $u = ['B','KB','MB','GB','TB'];
        $i = floor(log(max($bytes,1),1024));
        return round($bytes/pow(1024,$i),2).' '.$u[$i];
    }

    private function formatList($c)
    {
        return $c->isEmpty()
            ? "_Hech narsa yo‘q_"
            : $c->map(fn($v,$k)=>"• `$k` — *{$v}* ta")->join("\n");
    }

    private function formatSpeedList($rows)
    {
        return $rows->isEmpty()
            ? "_Topilmadi_"
            : $rows->map(fn($x) => "• `{$x['path']}` — *{$x['duration_ms']} ms*")->join("\n");
    }

    private function formatUserList($c, $users)
    {
        return $c->isEmpty()
            ? "_Hech narsa yo‘q_"
            : $c->map(function($count,$id)use($users){
                $u=$users[$id]??null;
                return $u && $u->employee
                    ? "• *{$u->employee->name}* — *{$count}* ta"
                    : "• User *#{$id}* — *{$count}* ta";
            })->join("\n");
    }

    private function cleanOldLogs($file)
    {
        $new = collect();
        foreach (file($file) as $line) {
            $pos = strpos($line,'{');
            if ($pos===false) continue;
            $data = json_decode(substr($line,$pos),true);
            if (!$data || empty($data['time'])) continue;
            if (Carbon::parse($data['time'])->greaterThan(Carbon::now()->subDay())) {
                $new->push($line);
            }
        }
        file_put_contents($file, implode("", $new->toArray()));

        $this->info("🧹 Eski loglar tozalandi!");
    }

    private function sendMessage($text): void
    {
        $chunks = mb_str_split($text, 3500);
        foreach ($chunks as $chunk) {
            Http::timeout(10)->post(
                "https://api.telegram.org/bot{$this->botToken}/sendMessage",
                [
                    'chat_id' => $this->chatId,
                    'text' => $chunk,
                    'parse_mode' => 'Markdown'
                ]
            );
            sleep(1);
        }
    }
}
