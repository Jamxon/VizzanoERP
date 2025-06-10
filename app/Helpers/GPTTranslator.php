<?php

namespace App\Helpers;

use App\Models\Log;
use Illuminate\Support\Facades\Http;
use JetBrains\PhpStorm\NoReturn;

class GPTTranslator
{
    public static function translate($text, $from = 'ru', $to = 'uz')
    {
        $apiKey = config('services.openai.key');
        dd($apiKey);
        $prompt = "Translate this from $from to $to: \"$text\"";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => "You are a translation assistant."],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
            ]);

            if ($response->successful()) {
                $result = $response->json('choices.0.message.content');
                Log::add(
                    auth()->id(),
                    'GPT translation',
                    'translate',
                    [
                        'text' => $text,
                        'from' => $from,
                        'to' => $to,
                        'result' => $result
                    ],
                    'gpt_translate'
                );
                return $result ?? $text;
            } else {
                Log::add(
                    auth()->id(),
                    'GPT translation',
                    'translate',
                    [
                        'text' => $text,
                        'from' => $from,
                        'to' => $to,
                    ],
                    'gpt_translate'
                );
                return $text;
            }
        } catch (\Throwable $e) {
            Log::error("GPT translate exception", [
                'message' => $e->getMessage(),
                'text' => $text,
            ]);
            return $text;
        }
    }
}
