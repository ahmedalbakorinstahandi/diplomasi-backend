<?php

namespace App\Services\Certificates;

class CertificateTemplateConfigMerger
{
    /**
     * Merge level-specific JSON config onto defaults (recursive for known keys).
     *
     * @param  array<string, mixed>|null  $levelConfig
     * @return array<string, mixed>
     */
    public static function merge(?array $levelConfig): array
    {
        $defaults = config('certificate_template.default_config', []);

        if ($levelConfig === null || $levelConfig === []) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $levelConfig);
    }
}
