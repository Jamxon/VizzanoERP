<?php
use App\Models\Log;
use Illuminate\Support\Facades\Http;

if (!function_exists('transliterate')) {
    /**
     * Transliterate a given text from Cyrillic to Latin or vice versa.
     *
     * @param string $text The text to transliterate.
     * @return string The transliterated text.
     */
    function transliterate_to_latin(string $text): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
            'ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'x','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sh','ъ'=>'',
            'ы'=>'i','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'қ'=>'q','ў'=>'o‘','ғ'=>'g‘','ҳ'=>'h',
        ];
        return strtr(mb_strtolower($text, 'UTF-8'), $map);
    }

    function transliterate_to_cyrillic(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        // Tartib muhim! Uzun so‘z birinchi o‘zgaradi
        $replacements = [
            'g‘' => 'ғ',
            'o‘' => 'ў',
            'sh' => 'ш',
            'ch' => 'ч',
            'yo' => 'ё',
            'yu' => 'ю',
            'ya' => 'я',
            'ts' => 'ц',
        ];

        foreach ($replacements as $latin => $cyrillic) {
            $text = str_replace($latin, $cyrillic, $text);
        }

        // Endi bitta harflarni almashtiramiz
        $single = [
            'a'=>'а','b'=>'б','v'=>'в','g'=>'г','d'=>'д','e'=>'е','j'=>'ж','z'=>'з',
            'i'=>'и','y'=>'й','k'=>'к','l'=>'л','m'=>'м','n'=>'н','o'=>'о','p'=>'п',
            'r'=>'р','s'=>'с','t'=>'т','u'=>'у','f'=>'ф','x'=>'х','h'=>'ҳ','q'=>'қ',
        ];

        return strtr($text, $single);
    }
    
    function sendMessage($chatId, $text)
{
    $token = config('services.telegram.bot_token');
    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    try {
        $response = Http::post($url, [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);

        \App\Models\Log::add(
            auth()->id(),
            'Telegramga yuborildi',
            'success',
            null,
            [
                'chat_id' => $chatId,
                'text' => $text
            ]
        );

    } catch (\Exception $e) {
        \App\Models\Log::add(
            auth()->id(),
            'Telegramga yuborilmadi',
            'error',
            null,
            [
                'chat_id' => $chatId,
                'text' => $text,
                'error' => $e->getMessage()
            ]
        );
    }
}


    function getUsdRate(): float|int
    {
        try {
            $response = Http::get('https://cbu.uz/uz/arkhiv-kursov-valyut/json/');
            $rates = $response->json();

            $usd = collect($rates)->firstWhere('Ccy', 'USD');

            return $usd ? floatval($usd['Rate']) : 12000; // fallback
        } catch (\Exception $e) {
            return 12000; // xatoda default
        }
    }


}
