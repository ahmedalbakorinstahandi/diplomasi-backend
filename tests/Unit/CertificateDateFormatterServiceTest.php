<?php

namespace Tests\Unit;

use App\Services\Certificates\CertificateDateFormatterService;
use PHPUnit\Framework\TestCase;

class CertificateDateFormatterServiceTest extends TestCase
{
    public function test_formats_issued_at_in_english(): void
    {
        $s = new CertificateDateFormatterService;
        $this->assertSame('13 April 2026', $s->formatDisplay('2026-04-13 10:00:00'));
    }
}
