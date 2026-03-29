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
            'legal_web.ios_terms_page_title',
            'legal_web.ios_terms_heading',
        );
    }

    public function iosPrivacy(): View|Response
    {
        foreach (self::PRIVACY_KEYS as $key) {
            $html = $this->htmlValueForKey($key);
            if ($html !== null) {
                return $this->legalDocumentView(
                    'legal_web.ios_privacy_page_title',
                    'legal_web.ios_privacy_heading',
                    $html,
                );
            }
        }

        return $this->missingResponse(__('legal_web.ios_privacy_heading'));
    }

    private function documentFromKey(string $keyName, string $pageTitleKey, string $headerSubtitleKey): View|Response
    {
        $html = $this->htmlValueForKey($keyName);
        if ($html === null) {
            return $this->missingResponse(__($headerSubtitleKey));
        }

        return $this->legalDocumentView($pageTitleKey, $headerSubtitleKey, $html);
    }

    private function legalDocumentView(string $pageTitleKey, string $headerSubtitleKey, string $html): View
    {
        $app = $this->appDisplayName();

        return view('legal.document', [
            'appName' => $app,
            'pageTitle' => __($pageTitleKey, ['app' => $app]),
            'headerSubtitle' => __($headerSubtitleKey),
            'contentHtml' => $html,
        ]);
    }

    /**
     * اسم العرض للواجهات العامة؛ لا نعرض كلمة Laravel حتى لو بقيت في .env قديمة.
     */
    private function appDisplayName(): string
    {
        $name = trim((string) config('app.name', 'Diplomasi'));
        if ($name === '' || strcasecmp($name, 'Laravel') === 0) {
            return 'Diplomasi';
        }

        return $name;
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
        $app = $this->appDisplayName();

        return response()->view('legal.missing', [
            'appName' => $app,
            'pageTitle' => __('legal_web.missing_page_title', ['app' => $app]),
            'heading' => $heading,
        ], 404);
    }
}
