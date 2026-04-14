<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Support\MoyasarConfig;
use Illuminate\Http\JsonResponse;

class MoyasarPublicConfigController extends Controller
{
    /**
     * Publishable key + mode for mobile SDK (no secret keys).
     */
    public function show(): JsonResponse
    {
        $key = MoyasarConfig::publicKey();
        if ($key === '') {
            return response()->json([
                'success' => false,
                'message' => 'Moyasar publishable key is not configured for the active mode.',
            ], 503);
        }

        $currency = strtoupper((string) config('services.moyasar.currency', 'USD'));

        return response()->json([
            'success' => true,
            'data' => [
                'mode' => MoyasarConfig::activeMode(),
                'publishable_key' => $key,
                'currency' => $currency,
            ],
        ], 200);
    }
}
