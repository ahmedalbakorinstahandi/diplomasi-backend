<?php

namespace App\Support\Billing;

/**
 * Partial email for user-facing hints (e.g. exa*******12@demo.test).
 */
final class MaskEmailHint
{
    public static function format(?string $email): string
    {
        if ($email === null || $email === '') {
            return '***@***';
        }

        $email = trim($email);
        $at = strrpos($email, '@');
        if ($at === false || $at === 0) {
            return '***@***';
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at + 1);
        if ($domain === '') {
            return '***@***';
        }

        $len = strlen($local);
        if ($len <= 2) {
            return '*'.'*'.'@'.$domain;
        }

        if ($len < 5) {
            $first = substr($local, 0, 1);
            $last = substr($local, -1);

            return $first.'***'.$last.'@'.$domain;
        }

        $first3 = substr($local, 0, 3);
        $last2 = substr($local, -2);
        $middleLen = max(1, $len - 5);

        return $first3.str_repeat('*', $middleLen).$last2.'@'.$domain;
    }
}
