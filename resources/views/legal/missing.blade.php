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
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            text-align: center;
        }
        .box {
            max-width: 24rem;
            padding: 2rem;
            background: #1a2332;
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: 1rem;
        }
        h1 { font-size: 1.25rem; margin-bottom: 0.75rem; }
        p { color: #94a3b8; font-size: 0.9375rem; line-height: 1.6; }
    </style>
</head>
<body>
<div class="box">
    <h1>{{ $heading }}</h1>
    <p>{{ __('legal_web.missing_message') }}</p>
</div>
</body>
</html>
