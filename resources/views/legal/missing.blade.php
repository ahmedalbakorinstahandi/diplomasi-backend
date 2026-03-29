<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600&family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'IBM Plex Sans Arabic', system-ui, sans-serif;
            min-height: 100vh;
            background: #0f1419;
            color: #e8edf4;
            display: flex;
            flex-direction: column;
        }
        .top-bar {
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            padding: 1.25rem 1.5rem;
            background: linear-gradient(180deg, #1a2332 0%, #0f1419 100%);
        }
        .top-inner {
            max-width: 48rem;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 0.875rem;
        }
        .brand-logo { width: 2.75rem; height: 2.75rem; border-radius: 0.5rem; object-fit: contain; }
        .brand-text { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }
        .app-name { font-size: 1.25rem; font-weight: 700; line-height: 1.2; }
        .header-subtitle { font-size: 0.8125rem; font-weight: 500; color: #94a3b8; }
        .center {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .box {
            max-width: 24rem;
            padding: 2rem;
            background: #1a2332;
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: 1rem;
            text-align: center;
        }
        .box p { color: #94a3b8; font-size: 0.9375rem; line-height: 1.6; }
        footer {
            text-align: center;
            padding: 1.5rem;
            border-top: 1px solid rgba(148, 163, 184, 0.12);
            font-size: 0.8125rem;
            color: #94a3b8;
        }
        .footer-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.35rem;
            color: #e8edf4;
            font-weight: 600;
        }
        .footer-brand img { width: 1.5rem; height: 1.5rem; border-radius: 0.35rem; }
    </style>
</head>
<body>
<header class="top-bar">
    <div class="top-inner">
        <img class="brand-logo" src="{{ asset('images/logo.png') }}" width="44" height="44" alt="{{ $appName }}">
        <div class="brand-text">
            <span class="app-name">{{ $appName }}</span>
            <span class="header-subtitle">{{ $heading }}</span>
        </div>
    </div>
</header>
<div class="center">
    <div class="box">
        <p>{{ __('legal_web.missing_message') }}</p>
    </div>
</div>
<footer>
    <div class="footer-brand">
        <img src="{{ asset('images/logo.png') }}" width="24" height="24" alt="">
        <span>{{ $appName }}</span>
    </div>
    &copy; {{ date('Y') }} {{ $appName }}
</footer>
</body>
</html>
