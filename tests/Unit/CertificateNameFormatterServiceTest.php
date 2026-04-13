<?php

namespace Tests\Unit;

use App\Services\Certificates\CertificateNameFormatterService;
use PHPUnit\Framework\TestCase;

class CertificateNameFormatterServiceTest extends TestCase
{
    private CertificateNameFormatterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CertificateNameFormatterService;
    }

    public function test_english_two_words_title_case(): void
    {
        $out = $this->service->format('john', 'doe');
        $this->assertSame('John Doe', $out);
    }

    public function test_uses_first_token_only_when_multiple_words(): void
    {
        $out = $this->service->format('John Middle', 'Doe Extra');
        $this->assertSame('John Doe', $out);
    }

    public function test_arabic_transliterates_to_latin(): void
    {
        $out = $this->service->format('أحمد', 'الباكور');
        $this->assertNotSame('', $out);
        $this->assertStringNotContainsString('أحمد', $out);
    }

    public function test_extra_spaces_normalized(): void
    {
        $out = $this->service->format('  Ali  ', "  Zaid  ");
        $this->assertSame('Ali Zaid', $out);
    }

    public function test_long_latin_name_shrinks_to_first_tokens(): void
    {
        $out = $this->service->format('john paul', 'doe smith');
        $this->assertSame('John Doe', $out);
    }

    public function test_preferred_english_override(): void
    {
        $out = $this->service->format('x', 'y', 'Preferred Name');
        $this->assertSame('Preferred Name', $out);
    }
}
