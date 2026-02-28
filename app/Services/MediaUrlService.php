<?php

namespace App\Services;

class MediaUrlService
{
    public static function toUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $value = ltrim($value, '/');
        // Public assets under public/ (e.g. images/) are served from root
        if (str_starts_with($value, 'images/')) {
            return asset($value);
        }
        return url('storage/' . $value);
    }
}
