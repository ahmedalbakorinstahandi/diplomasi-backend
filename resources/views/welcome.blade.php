<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@php
    $welcomeApp = trim((string) config('app.name', 'Diplomasi'));
    if ($welcomeApp === '' || strcasecmp($welcomeApp, 'Laravel') === 0) {
        $welcomeApp = 'Diplomasi';
    }
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $welcomeApp }}</title>
    <style>
        body {
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f6f8;
            color: #1a1a1a;
        }
        main {
            text-align: center;
            padding: 2rem;
        }
        h1 { font-size: 1.5rem; margin: 0 0 0.5rem; }
        p { margin: 0; color: #5c6570; font-size: 0.95rem; }
    </style>
</head>
<body>
<main>
    <h1>{{ $welcomeApp }}</h1>
    <p>{{ __('welcome.backend_ok') }}</p>
</main>
</body>
</html>
