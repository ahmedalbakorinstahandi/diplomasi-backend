<?php

namespace App\Services\Certificates;

use Carbon\Carbon;

class CertificateDateFormatterService
{
    /**
     * Fixed English display for certificate (e.g. "13 April 2026").
     */
    public function formatDisplay(\DateTimeInterface|string|null $issuedAt): string
    {
        if ($issuedAt === null || $issuedAt === '') {
            return '';
        }

        $c = Carbon::parse($issuedAt)->locale('en');

        return $c->format('j F Y');
    }
}
