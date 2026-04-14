<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Services\System\AppUpdateSuggestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    public function __construct(
        protected AppUpdateSuggestService $appUpdateSuggestService
    ) {}

    /**
     * Check if the app should suggest an update (optional, non-forced).
     * Client should send X-App-Version header. Call at most once per 24h from the app.
     */
    public function checkSuggest(Request $request): JsonResponse
    {
        $appVersion = $request->header('X-App-Version', '0.0.0');
        $data = $this->appUpdateSuggestService->buildForClientVersion($appVersion);

        return response()->json([
            'success' => true,
            'data' => $data,
        ], 200);
    }
}
