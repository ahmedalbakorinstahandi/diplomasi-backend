<?php

namespace App\Http\Services\System;

use App\Models\System\Setting;

/**
 * Builds the optional "suggest update" payload (same semantics as GET general/app-update-check).
 */
class AppUpdateSuggestService
{
    /**
     * @return array{suggest: bool, store_link_android: ?string, store_link_ios: ?string}
     */
    public function buildForClientVersion(string $appVersion): array
    {
        $suggestedMinSetting = Setting::where('key_name', 'app.suggested_min_version')->first();
        $suggestedMinVersion = $suggestedMinSetting ? (string) $suggestedMinSetting->value : null;

        $suggest = false;
        $storeLinkAndroid = null;
        $storeLinkIos = null;

        if ($suggestedMinVersion !== null && version_compare($appVersion, $suggestedMinVersion, '<')) {
            $suggest = true;
            $playLink = Setting::where('key_name', 'app.google_play_link')->first();
            $appleLink = Setting::where('key_name', 'app.apple_store_link')->first();
            $storeLinkAndroid = $playLink ? (string) $playLink->value : null;
            $storeLinkIos = $appleLink ? (string) $appleLink->value : null;
        }

        return [
            'suggest' => $suggest,
            'store_link_android' => $storeLinkAndroid,
            'store_link_ios' => $storeLinkIos,
        ];
    }
}
