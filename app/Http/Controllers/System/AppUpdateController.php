<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    /**
     * Check if the app should suggest an update (optional, non-forced).
     * Client should send X-App-Version header. Call at most once per 24h from the app.
     */
    public function checkSuggest(Request $request): JsonResponse
    {
        $appVersion = $request->header('X-App-Version', '0.0.0');

        $minVersionSetting = Setting::where('key_name', 'app.min_version')->first();
        $minVersion = $minVersionSetting ? (string) $minVersionSetting->value : null;

        $suggestedMinSetting = Setting::where('key_name', 'app.suggested_min_version')->first();
        $suggestedMinVersion = $suggestedMinSetting ? (string) $suggestedMinSetting->value : null;

        $suggest = false;
        $storeLinkAndroid = null;
        $storeLinkIos = null;

        if ($minVersion !== null && $suggestedMinVersion !== null && version_compare($suggestedMinVersion, $minVersion, '<')) {
            $needsSuggest = version_compare($appVersion, $suggestedMinVersion, '>=') && version_compare($appVersion, $minVersion, '<');
            if ($needsSuggest) {
                $suggest = true;
                $playLink = Setting::where('key_name', 'app.google_play_link')->first();
                $appleLink = Setting::where('key_name', 'app.apple_store_link')->first();
                $storeLinkAndroid = $playLink ? (string) $playLink->value : null;
                $storeLinkIos = $appleLink ? (string) $appleLink->value : null;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'suggest' => $suggest,
                'store_link_android' => $storeLinkAndroid,
                'store_link_ios' => $storeLinkIos,
            ],
        ], 200);
    }
}
