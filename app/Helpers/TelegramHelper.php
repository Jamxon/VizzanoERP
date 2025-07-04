<?php


namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class TelegramHelper
{
    public static function sendMessage($text): void
    {
        $token = "8174989006:AAG2h0eggiXZVa_vlkR0EtsqQjGLgZYO4v0";
        $chatId = 5228018221;

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        Http::post($url, [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ]);
    }
}
