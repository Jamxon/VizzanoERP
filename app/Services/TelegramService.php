<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class TelegramService
{
    private $token;

    public function __construct()
    {
        $this->token = "8381341070:AAHMQEsFDAGvAADaby5SE1yEqoqK_6hNOfo";
    }

    private function apiUrl($method)
    {
        return "https://api.telegram.org/bot{$this->token}/{$method}";
    }

    public function sendMessage($chatId, $text)
    {
        $res = Http::post($this->apiUrl('sendMessage'), [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ]);

        return $res->json();
    }

    public function editMessage($chatId, $messageId, $text)
    {
        $res = Http::post($this->apiUrl('editMessageText'), [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ]);

        return $res->json();
    }

    public function updateDailyReport($branchId, $chatId, $employees)
    {
        $today = now()->toDateString();
        $cacheKey = "telegram_report_message_{$branchId}_{$today}";

        // Department va Group bo‘yicha guruhlash
        $departments = $employees->groupBy('department_id');

        $text = "<b>📋 {$today} - Davomat (Filial {$branchId})</b>\n\n";

        foreach ($departments as $departmentId => $deptEmployees) {
            $departmentName = optional($deptEmployees->first()->department)->name ?? "No department";
            $text .= "🏢 <b>{$departmentName}</b> — " . $deptEmployees->count() . " ta hodim\n";

            // Group bo‘yicha ham guruhlash
            $groups = $deptEmployees->groupBy('group_id');
            foreach ($groups as $groupId => $groupEmployees) {
                if ($groupId) {
                    $groupName = optional($groupEmployees->first()->group)->name ?? "No group";
                    $text .= "   └─ 👥 {$groupName}: " . $groupEmployees->count() . "\n";
                }
            }

            $text .= "\n";
        }

        $text .= "\n<b>Jami:</b> " . $employees->count() . " ta hodim";

        // Avvalgi message ID olib kelamiz
        $messageId = Cache::get($cacheKey);

        if ($messageId) {
            $res = $this->editMessage($chatId, $messageId, $text);
            if (!($res['ok'] ?? false)) {
                $res = $this->sendMessage($chatId, $text);
                if ($res['ok'] ?? false) {
                    Cache::put($cacheKey, $res['result']['message_id'], now()->addDay());
                }
            }
        } else {
            $res = $this->sendMessage($chatId, $text);
            if ($res['ok'] ?? false) {
                Cache::put($cacheKey, $res['result']['message_id'], now()->addDay());
            }
        }
    }
}
