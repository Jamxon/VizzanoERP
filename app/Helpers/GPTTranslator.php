<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class GPTTranslator
{
    public static function translate($text, $from = 'ru', $to = 'uz')
    {
            $apiKey = env('OPENAI_API_KEY');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => "You are a translation assistant."],
                    ['role' => 'user', 'content' => "Translate this from $from to $to: \"$text\""],
                ],
                'temperature' => 0.2,
            ]);

            return $response->json('choices.0.message.content') ?? $text;
    }
}
