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
        $replacements = [
            'sh' => 'ш',
            'ch' => 'ч',
            'yo' => 'ё',
            'yu' => 'ю',
            'ya' => 'я',
            'g‘' => 'ғ',
            'o‘' => 'ў',
            'ts' => 'ц',
            'a' => 'а',
            'b' => 'б',
            'v' => 'в',
            'g' => 'г',
            'd' => 'д',
            'e' => 'е',
            'j' => 'ж',
            'z' => 'з',
            'i' => 'и',
            'y' => 'й',
            'k' => 'к',
            'l' => 'л',
            'm' => 'м',
            'n' => 'н',
            'o' => 'о',
            'p' => 'п',
            'r' => 'р',
            's' => 'с',
            't' => 'т',
            'u' => 'у',
            'f' => 'ф',
            'x' => 'х',
            'h' => 'ҳ',
            'q' => 'қ',
        ];

        // Avval uzun kombinatsiyalarni almashtiramiz
        foreach (['sh', 'ch', 'yo', 'yu', 'ya', 'g‘', 'o‘', 'ts'] as $combo) {
            $text = str_ireplace($combo, $replacements[$combo], $text);
        }

        // Endi bitta harflarni
        $text = strtr($text, $replacements);

        return $text;
    }

}
