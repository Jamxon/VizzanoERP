<?php

namespace App\Services;

use App\Models\TelegramReport;
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

        // Department bo‘yicha guruhlash
        $departments = $employees->groupBy('department_id');

        $text = "<b>📋 {$today} - Davomat (Filial {$branchId})</b>\n\n";

        $aupCount = 0;

        foreach ($departments as $departmentId => $deptEmployees) {
            $departmentName = optional($deptEmployees->first()->department)->name ?? "No department";

            if ($departmentName === 'Тикув бўлими') {
                $text .= "🏢 <b>{$departmentName}</b> — " . $deptEmployees->count() . " ta hodim\n";

                $groups = $deptEmployees->groupBy('group_id');
                foreach ($groups as $groupId => $groupEmployees) {
                    if ($groupId) {
                        $groupName = optional($groupEmployees->first()->group)->name ?? "No group";
                        $text .= "   └─ 👥 {$groupName}: " . $groupEmployees->count() . "\n";
                    }
                }

                $text .= "\n";
            } else {
                $aupCount += $deptEmployees->count();
            }
        }

        if ($aupCount > 0) {
            $text .= "🏢 <b>AUP</b> — {$aupCount} ta hodim\n\n";
        }

        $text .= "\n<b>Jami:</b> " . $employees->count() . " ta hodim";

        // Bazadan avvalgi reportni olib kelamiz
        $report = TelegramReport::where('branch_id', $branchId)
            ->where('date', $today)
            ->first();

        if ($report && $report->message_id) {
            // eski xabarni yangilash
            $res = $this->editMessage($chatId, $report->message_id, $text);

            if (($res['ok'] ?? false)) {
                // agar yangilansa, faqat textni update qilamiz
                $report->update(['text' => $text]);
            }
            // ❌ agar yangilanmasa, hech narsa qilmaymiz
        } else {
            // yangi xabar yuborish (faqat birinchi marta)
            $res = $this->sendMessage($chatId, $text);
            if ($res['ok'] ?? false) {
                TelegramReport::updateOrCreate(
                    ['branch_id' => $branchId, 'date' => $today],
                    [
                        'chat_id'    => $chatId,
                        'message_id' => $res['result']['message_id'],
                        'text'       => $text,
                    ]
                );
            }
        }
    }
}
