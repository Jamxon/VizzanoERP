<?php

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
        return strtr($text, [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
            'ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'x','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sh','ъ'=>'',
            'ы'=>'i','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'қ'=>'q','ў'=>'o‘','ғ'=>'g‘','ҳ'=>'h',
        ]);
    }

    function transliterate_to_cyrillic($text): string
    {
        return strtr($text, [
            'a'=>'а','b'=>'б','v'=>'в','g'=>'г','d'=>'д','e'=>'е','yo'=>'ё',
            'j'=>'ж','z'=>'з','i'=>'и','y'=>'й','k'=>'к','l'=>'л','m'=>'м',
            'n'=>'н','o‘'=>'ў','o'=>'о','p'=>'п','r'=>'р','s'=>'с','t'=>'т',
            'u'=>'у','f'=>'ф','x'=>'х','ts'=>'ц','ch'=>'ч','sh'=>'ш','h'=>'ҳ',
            'yu'=>'ю','ya'=>'я','q'=>'қ','g‘'=>'ғ',
        ]);
    }

    function translateToUzFree($text)
    {
        $response = Http::get('https://api.mymemory.translated.net/get', [
            'q' => $text,
            'langpair' => 'ru|uz'
        ]);

        if ($response->successful()) {
            return $response['responseData']['translatedText'] ?? $text;
        }

        return $text;
    }


}
