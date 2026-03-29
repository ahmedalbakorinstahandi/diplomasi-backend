<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $pageTitle }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f1419;
            --surface: #1a2332;
            --text: #e8edf4;
            --text-muted: #94a3b8;
            --accent: #38bdf8;
            --border: rgba(148, 163, 184, 0.12);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'IBM Plex Sans Arabic', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.65;
        }
        .shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .top-bar {
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg) 100%);
            border-bottom: 1px solid var(--border);
            padding: 1.25rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(12px);
        }
        .top-inner {
            max-width: 48rem;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 0.875rem;
        }
        .brand-logo {
            width: 2.75rem;
            height: 2.75rem;
            flex-shrink: 0;
            border-radius: 0.5rem;
            object-fit: contain;
        }
        .app-name {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text);
        }
        main {
            flex: 1;
            max-width: 48rem;
            width: 100%;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            padding: 2rem 1.75rem;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.35);
        }
        @media (min-width: 640px) {
            .card { padding: 2.5rem 2.25rem; }
        }
        .legal-body {
            font-size: 1rem;
            color: var(--text);
            direction: auto;
            text-align: start;
        }
        .legal-body :where(p, ul, ol, blockquote) {
            margin-bottom: 1rem;
        }
        .legal-body :where(h1, h2, h3, h4) {
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
            font-weight: 700;
            color: var(--text);
        }
        .legal-body :where(h1):first-child,
        .legal-body :where(h2):first-child {
            margin-top: 0;
        }
        .legal-body a {
            color: var(--accent);
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .legal-body ul, .legal-body ol {
            padding-inline-start: 1.25rem;
        }
        footer {
            text-align: center;
            padding: 2rem 1rem;
            border-top: 1px solid var(--border);
            margin-top: auto;
        }
        .footer-brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 0.35rem;
        }
        .footer-brand img {
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 0.35rem;
        }
        .footer-name {
            font-weight: 600;
            font-size: 0.9375rem;
            color: var(--text);
        }
        .footer-copy {
            font-size: 0.8125rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
@php
    $appName = config('app.name', 'Diplomasi');
@endphp
<div class="shell">
    <header class="top-bar">
        <div class="top-inner">
            <img
                class="brand-logo"
                src="{{ asset('images/logo.png') }}"
                width="44"
                height="44"
                alt="{{ $appName }}"
            >
            <span class="app-name">{{ $appName }}</span>
        </div>
    </header>
    <main>
        <article class="card">
            <div class="legal-body">
                {!! $contentHtml !!}
            </div>
        </article>
    </main>
    <footer>
        <div class="footer-brand">
            <img src="{{ asset('images/logo.png') }}" width="24" height="24" alt="">
            <span class="footer-name">{{ $appName }}</span>
        </div>
        <p class="footer-copy">&copy; {{ date('Y') }} {{ $appName }}</p>
    </footer>
</div>
</body>
</html>
