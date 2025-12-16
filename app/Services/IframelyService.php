<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class IframelyService
{
    public static function fetch(string $href): array
    {
        $baseUrl = config('services.iframely.base_url');
        $apiKey = config('services.iframely.api_key');
        $timeout = (int) config('services.iframely.timeout', 12);
        $origin = config('services.iframely.origin');

        if (empty($baseUrl)) {
            MessageService::abort(
                500,
                'Iframely base_url is not configured. Set IFARAMELY_BASE_URL or services.iframely.base_url',
            );
        }

        if (empty($apiKey)) {
            MessageService::abort(
                500,
                'Iframely api_key is not configured. Set IFARAMELY_API_KEY or services.iframely.api_key',
            );
        }

        $query = [
            'url' => $href,
            'key' => $apiKey,  // Iframely Cloud API uses 'key' not 'api_key'
        ];

        if (!empty($origin)) {
            $query['origin'] = $origin;
        }

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->get($baseUrl, $query);

        if ($response->failed()) {
            $status = $response->status();
            $body = $response->json() ?? $response->body();
            $message = is_array($body) ? json_encode($body) : (string) $body;
            MessageService::abort(
                500,
                "Iframely API request failed ({$status}): {$message}",
            );
        }

        $res = $response->json();

        return $res;
    }
}
