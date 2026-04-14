<?php

return [
    /*
    | Default serif font for certificate overlay (TTF). Crimson Text SemiBold — OFL.
    */
    'font_path' => storage_path('app/fonts/CrimsonText-SemiBold.ttf'),

    /*
    | Fallback when primary font is missing (must exist for rendering to succeed).
    */
    'font_fallback_search' => [
        storage_path('app/fonts/CrimsonText-SemiBold.ttf'),
        resource_path('fonts/CrimsonText-SemiBold.ttf'),
    ],

    /*
    | Default layout for 2048×1448 template (normalized 0–1 coordinates).
    */
    'default_config' => [
        'template_width' => 2048,
        'template_height' => 1448,
        'name' => [
            'x' => 0.084473,
            'y' => 0.290055,
            'width' => 0.307129,
            'height' => 0.069061,
            'baseline_y' => 0.344376,
            'align' => 'center',
            'vertical_align' => 'bottom',
            'color' => '#0E2459',
            'max_font_size' => 140,
            'min_font_size' => 38,
            'font_weight' => 'semibold',
            'case' => 'title',
        ],
        'date' => [
            'x' => 0.162016,
            'y' => 0.839088,
            'width' => 0.175781,
            'height' => 0.062155,
            'baseline_y' => 0.877072,
            'align' => 'center',
            'vertical_align' => 'bottom',
            'color' => '#0E2459',
            'max_font_size' => 85,
            'min_font_size' => 24,
            'font_weight' => 'semibold',
            'format' => 'd F Y',
        ],
        'safe_zones' => [
            'signature_and_seal' => [
                'x' => 0.383789,
                'y' => 0.806630,
                'width' => 0.212891,
                'height' => 0.110497,
            ],
        ],
    ],

    'max_upload_kb' => 8192,
];
