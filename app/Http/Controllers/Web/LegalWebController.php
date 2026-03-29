<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\System\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class LegalWebController extends Controller
{
    private const TERMS_KEY = 'legal.terms_conditions_ios';

    private const PRIVACY_KEYS = [
        'legal.privcy_policy_ios',
        'legal.privacy_policy_ios',
    ];

    public function iosTerms(): View|Response
    {
        return $this->documentFromKey(
            self::TERMS_KEY,
            __('legal_web.ios_terms_page_title'),
            __('legal_web.ios_terms_heading'),
        );
    }

    public function iosPrivacy(): View|Response
    {
        foreach (self::PRIVACY_KEYS as $key) {
            $html = $this->htmlValueForKey($key);
            if ($html !== null) {
                return view('legal.document', [
                    'pageTitle' => __('legal_web.ios_privacy_page_title'),
                    'documentHeading' => __('legal_web.ios_privacy_heading'),
                    'contentHtml' => $html,
                ]);
            }
        }

        return $this->missingResponse(__('legal_web.ios_privacy_heading'));
    }

    private function documentFromKey(string $keyName, string $pageTitle, string $documentHeading): View|Response
    {
        $html = $this->htmlValueForKey($keyName);
        if ($html === null) {
            return $this->missingResponse($documentHeading);
        }

        return view('legal.document', [
            'pageTitle' => $pageTitle,
            'documentHeading' => $documentHeading,
            'contentHtml' => $html,
        ]);
    }

    private function htmlValueForKey(string $keyName): ?string
    {
        $setting = Setting::query()->where('key_name', $keyName)->first();
        if (!$setting) {
            return null;
        }
        $content = $setting->value;
        $content = is_string($content) ? trim($content) : '';

        return $content !== '' ? $content : null;
    }

    private function missingResponse(string $heading): Response
    {
        return response()->view('legal.missing', [
            'pageTitle' => __('legal_web.missing_page_title'),
            'heading' => $heading,
        ], 404);
    }
}
