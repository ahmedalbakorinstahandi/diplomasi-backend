<?php

namespace Tests\Unit;

use App\Services\Certificates\CertificateTemplateConfigMerger;
use Tests\TestCase;

class CertificateTemplateConfigMergerTest extends TestCase
{
    public function test_merge_preserves_defaults_when_level_null(): void
    {
        $m = CertificateTemplateConfigMerger::merge(null);
        $this->assertArrayHasKey('name', $m);
        $this->assertArrayHasKey('date', $m);
        $this->assertSame(2048, $m['template_width']);
    }

    public function test_merge_overrides_nested_keys(): void
    {
        $m = CertificateTemplateConfigMerger::merge([
            'name' => ['max_font_size' => 72],
        ]);
        $this->assertSame(72, $m['name']['max_font_size']);
        $this->assertArrayHasKey('min_font_size', $m['name']);
    }
}
