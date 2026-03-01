<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Certificate template path (relative to public/)
    |--------------------------------------------------------------------------
    */
    'template_path' => 'images/certificate-template.png',

    /*
    |--------------------------------------------------------------------------
    | Arabic → English glossary for course/level titles
    | Add entries as 'Arabic text' => 'English translation'
    |--------------------------------------------------------------------------
    */
    'glossary' => [],

    /*
    |--------------------------------------------------------------------------
    | Default provider names (English) for certificate footer
    |--------------------------------------------------------------------------
    */
    'training_provider_default' => 'Diplomasi',
    'exam_provider_default' => 'Diplomasi',

    /*
    |--------------------------------------------------------------------------
    | Application logo on certificate (GD fallback)
    |--------------------------------------------------------------------------
    */
    'show_app_logo' => env('CERTIFICATE_SHOW_APP_LOGO', true),
    'app_logo_path' => env('CERTIFICATE_APP_LOGO_PATH', 'images/logo.png'),
];
