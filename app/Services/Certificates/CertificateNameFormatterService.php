<?php

namespace App\Services\Certificates;

use App\Services\CertificateEnglishHelper;

class CertificateNameFormatterService
{
    /**
     * English display name: first token of first name + first token of last name, Title Case.
     * Optional future manual override.
     */
    public function format(?string $firstName, ?string $lastName, ?string $preferredEnglishFullName = null): string
    {
        if ($preferredEnglishFullName !== null && trim($preferredEnglishFullName) !== '') {
            return $this->titleCaseLatin(trim(preg_replace('/\s+/u', ' ', $preferredEnglishFullName)));
        }

        $t1 = $this->firstToken($firstName ?? '');
        $t2 = $this->firstToken($lastName ?? '');

        $p1 = $this->tokenToEnglishDisplay($t1);
        $p2 = $this->tokenToEnglishDisplay($t2);

        $parts = array_values(array_filter([$p1, $p2], fn ($s) => $s !== ''));

        return implode(' ', $parts);
    }

    private function firstToken(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        if ($s === '') {
            return '';
        }
        $parts = preg_split('/\s+/u', $s);

        return $parts[0] ?? '';
    }

    private function tokenToEnglishDisplay(string $token): string
    {
        if ($token === '') {
            return '';
        }

        if ($this->isMostlyLatin($token)) {
            return $this->titleCaseLatin($token);
        }

        $normalized = $this->normalizeArabic($token);
        $roman = CertificateEnglishHelper::romanizeArabicName($normalized);

        return $this->titleCaseLatin($roman);
    }

    private function isMostlyLatin(string $s): bool
    {
        $len = mb_strlen($s);
        if ($len === 0) {
            return true;
        }
        $latin = preg_match_all('/[A-Za-z]/', $s);

        return ($latin / $len) > 0.5;
    }

    /**
     * Remove tatweel, combining marks, unify alef/ya/hamza variants for deterministic romanization.
     */
    private function normalizeArabic(string $s): string
    {
        $s = preg_replace('/[\x{0640}]/u', '', $s);
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $s);
        $map = [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ؤ' => 'و', 'ئ' => 'ي', 'ى' => 'ي', 'ة' => 'ه',
        ];
        $out = '';
        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $ch) {
            $out .= $map[$ch] ?? $ch;
        }

        return trim(preg_replace('/\s+/u', ' ', $out));
    }

    private function titleCaseLatin(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }

        return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    }
}
