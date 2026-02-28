<?php

namespace App\Services;

/**
 * Prepares certificate content in English: romanization of names and translation of titles.
 */
class CertificateEnglishHelper
{
    /**
     * Arabic to Latin romanization map (simplified, readable form for names).
     * Based on common ALA-LC-style transliteration without diacritics.
     */
    private const ARABIC_TO_LATIN = [
        'ء' => "'", 'آ' => 'a', 'أ' => 'a', 'ؤ' => "'", 'إ' => 'i', 'ئ' => "'",
        'ا' => 'a', 'ب' => 'b', 'ة' => 'a', 'ت' => 't', 'ث' => 'th', 'ج' => 'j',
        'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z',
        'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z',
        'ع' => "'", 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'ل' => 'l',
        'م' => 'm', 'ن' => 'n', 'ه' => 'h', 'و' => 'w', 'ى' => 'a', 'ي' => 'y',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * Romanize Arabic name to English script (for display on certificate).
     * Does not translate meaning; converts script for accurate English spelling of the name.
     */
    public static function romanizeArabicName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $out = '';
        $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($chars as $i => $char) {
            $next = $chars[$i + 1] ?? '';
            if (isset(self::ARABIC_TO_LATIN[$char])) {
                $out .= self::ARABIC_TO_LATIN[$char];
            } elseif (mb_strlen($char) === 1 && ord($char) < 128) {
                $out .= $char;
            } else {
                $out .= $char;
            }
        }

        $out = preg_replace("/'+$/", '', $out);
        $out = preg_replace("/^'+/", '', $out);
        $out = preg_replace("/'+/", "'", $out);
        $out = trim(preg_replace('/\s+/', ' ', $out));

        return $out === '' ? $name : $out;
    }

    /**
     * Convert content text to English script (transliteration style).
     * Used for course titles per current requirement.
     */
    public static function translateToEnglish(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (!self::isMostlyArabic($text)) {
            return $text;
        }

        return self::romanizeArabicName($text);
    }

    /**
     * Convert level number to English phrase up to 20; fallback to numeric label.
     */
    public static function levelLabelFromNumber(?int $levelNumber): ?string
    {
        if ($levelNumber === null || $levelNumber < 1) {
            return null;
        }

        $ordinals = [
            1 => 'First Level',
            2 => 'Second Level',
            3 => 'Third Level',
            4 => 'Fourth Level',
            5 => 'Fifth Level',
            6 => 'Sixth Level',
            7 => 'Seventh Level',
            8 => 'Eighth Level',
            9 => 'Ninth Level',
            10 => 'Tenth Level',
            11 => 'Eleventh Level',
            12 => 'Twelfth Level',
            13 => 'Thirteenth Level',
            14 => 'Fourteenth Level',
            15 => 'Fifteenth Level',
            16 => 'Sixteenth Level',
            17 => 'Seventeenth Level',
            18 => 'Eighteenth Level',
            19 => 'Nineteenth Level',
            20 => 'Twentieth Level',
        ];

        return $ordinals[$levelNumber] ?? ("Level " . $levelNumber);
    }

    private static function isMostlyArabic(string $s): bool
    {
        $len = mb_strlen($s);
        if ($len === 0) {
            return false;
        }
        $arabicCount = preg_match_all('/[\x{0600}-\x{06FF}]/u', $s);
        return $arabicCount / $len > 0.3;
    }

    /**
     * Format date in English for certificate (e.g. "1 March 2026").
     */
    public static function formatDateInEnglish($date): string
    {
        if (!$date) {
            return '';
        }
        $carbon = \Carbon\Carbon::parse($date);
        return $carbon->format('j F Y');
    }

    /**
     * Number to English words (simple, for training hours if needed).
     */
    public static function numberToEnglishWords(int $number): string
    {
        $ones = [
            0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
            5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
            10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen',
            14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen',
            18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty',
        ];
        if ($number <= 20) {
            return $ones[$number] ?? (string) $number;
        }
        if ($number < 100) {
            $tens = [2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
                6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'];
            $t = (int) ($number / 10);
            $r = $number % 10;
            return ($tens[$t] ?? '') . ($r > 0 ? '-' . ($ones[$r] ?? $r) : '');
        }
        return (string) $number;
    }
}
