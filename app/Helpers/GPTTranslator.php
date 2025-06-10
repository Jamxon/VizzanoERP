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
        $prompt = "Please translate the following text from Russian to Uzbek:\n\"$text\"\nOnly return the translated text without explanation.";

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
            dd( $result = $response->json());


            if ($response->successful()) {
                $result = $response->json();
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
                    'GPT translation eeee',
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
