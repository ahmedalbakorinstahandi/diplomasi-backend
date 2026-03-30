<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>{{ $pageTitle }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --paper: #ffffff;
            --page-bg: #f3f4f6;
            --text: #111827;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --footer-band: #eceff2;
            --accent: #1e3a5f;
        }
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-font-smoothing: antialiased; }
        body {
            font-family: 'Inter', 'IBM Plex Sans Arabic', system-ui, sans-serif;
            background: var(--page-bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-size: 15px;
        }
        .doc-header {
            background: var(--paper);
            border-bottom: 1px solid var(--border);
            padding: 1.75rem clamp(1.25rem, 4vw, 2.5rem) 1.5rem;
            position: relative;
        }
        .doc-header__inner {
            max-width: 52rem;
            margin: 0 auto;
            padding-right: 5.5rem;
            min-height: 4.5rem;
        }
        .doc-header__logo {
            position: absolute;
            top: 1.75rem;
            right: clamp(1.25rem, 4vw, 2.5rem);
            left: auto;
            width: 4rem;
            height: 4rem;
            padding: 0.45rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--paper);
            border: 1px solid var(--border);
            border-radius: 4px;
        }
        .doc-header__logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .doc-header__title {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
        }
        .doc-header__subtitle {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        html[dir="ltr"] .doc-header__subtitle {
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .center {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem clamp(1.25rem, 4vw, 2.5rem);
        }
        .box {
            max-width: 28rem;
            width: 100%;
            background: var(--paper);
            border: 1px solid var(--border);
            border-radius: 2px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
        }
        .box p { color: var(--text-muted); font-size: 0.9375rem; line-height: 1.6; }
        .doc-footer {
            background: var(--footer-band);
            border-top: 1px solid var(--border);
            padding: 1.75rem clamp(1.25rem, 4vw, 2.5rem) 2rem;
        }
        .doc-footer__inner {
            max-width: 52rem;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        @media (min-width: 640px) {
            .doc-footer__inner { grid-template-columns: repeat(3, 1fr); gap: 2rem; }
        }
        .doc-footer__col {
            font-size: 0.8125rem;
            color: #4b5563;
            line-height: 1.55;
        }
        .doc-footer__col strong {
            display: block;
            color: var(--text);
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }
        .doc-footer__col a { color: var(--accent); text-decoration: none; }
        .doc-footer__col a:hover { text-decoration: underline; }
        .doc-footer__copy {
            grid-column: 1 / -1;
            text-align: center;
            padding-top: 1rem;
            margin-top: 0.25rem;
            border-top: 1px solid var(--border);
            font-size: 0.75rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
<header class="doc-header">
    <div class="doc-header__logo" aria-hidden="true">
        <img src="{{ asset('images/logo.png') }}" width="48" height="48" alt="">
    </div>
    <div class="doc-header__inner">
        <div class="doc-header__title">{{ $appName }}</div>
        <div class="doc-header__subtitle">{{ $heading }}</div>
    </div>
</header>
<div class="center">
    <div class="box">
        <p>{{ __('legal_web.missing_message') }}</p>
    </div>
</div>
<footer class="doc-footer">
    <div class="doc-footer__inner">
        <div class="doc-footer__col">
            <strong>{{ $appName }}</strong>
            {{ __('legal_web.footer_tagline') }}
        </div>
        <div class="doc-footer__col">
            <strong>{{ __('legal_web.footer_col_website') }}</strong>
            <a href="https://{{ __('legal_web.footer_website') }}" rel="noopener noreferrer">{{ __('legal_web.footer_website') }}</a>
        </div>
        <div class="doc-footer__col">
            <strong>{{ __('legal_web.footer_col_note') }}</strong>
            {{ __('legal_web.footer_note') }}
        </div>
        <div class="doc-footer__copy">
            &copy; {{ date('Y') }} {{ $appName }}
        </div>
    </div>
</footer>
</body>
</html>
