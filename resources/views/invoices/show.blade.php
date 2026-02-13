<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Invoice') }} #{{ $transaction->id }}</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 24px auto; padding: 0 16px; color: #333; }
        .header { border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .meta { color: #666; font-size: 0.9rem; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: 600; }
        .text-right { text-align: right; }
        .total { font-size: 1.1rem; font-weight: 700; margin-top: 16px; }
        .footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #ddd; font-size: 0.85rem; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name') }} — {{ __('Invoice') }}</h1>
        <div class="meta">
            {{ __('Invoice') }} #{{ $transaction->id }} · {{ $transaction->processed_at?->format('Y-m-d H:i') ?? $transaction->created_at->format('Y-m-d H:i') }}
        </div>
    </div>

    <p><strong>{{ __('Bill to') }}</strong></p>
    <p>
        @if($user)
            {{ $user->first_name }} {{ $user->last_name }}<br>
            @if($user->email) {{ $user->email }}<br> @endif
            @if($user->phone) {{ $user->phone }} @endif
        @else
            —
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="text-right">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $transaction->description ?? __('Subscription payment') }}</td>
                <td class="text-right">{{ number_format($transaction->amount, 2) }} {{ $transaction->currency }}</td>
            </tr>
        </tbody>
    </table>

    <p class="total text-right">{{ __('Total') }}: {{ number_format($transaction->amount, 2) }} {{ $transaction->currency }}</p>

    @if($subscription && $subscription->plan)
        <p><strong>{{ __('Subscription') }}</strong>: {{ $subscription->plan->name ?? __('Plan') }} · {{ __('Status') }}: {{ $subscription->status }}</p>
    @endif

    <div class="footer">
        {{ config('app.name') }} · {{ $transaction->processed_at?->format('Y-m-d') ?? $transaction->created_at->format('Y-m-d') }}
    </div>
</body>
</html>
