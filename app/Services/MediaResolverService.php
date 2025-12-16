<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use DOMDocument;
use DOMXPath;

class MediaResolverService
{
    public static function resolve(string $url): array
    {
        // تطبيع الرابط ومعالجة أي ترميز ضمني (%xx)
        $url = self::normalizeUrl($url);
        // جلب المحتوى (مع محاولات وتغييرات بسيطة للتهرّب من حظر بعض المواقع)
        $html = self::fetchHtmlWithFallbacks($url);

        // تحليل DOM والبحث عن وسوم meta
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML($html);
        $xpath = new DOMXPath($doc);

        // استخراج أفضل مرشّح للصورة: og:image[:url|:secure_url] ثم twitter:image
        $imageElement = $xpath->query('(
            //meta[@property="og:image:secure_url"] |
            //meta[@property="og:image:url"] |
            //meta[@property="og:image"] |
            //meta[@name="twitter:image"]
        )[1]')->item(0);

        // استخراج أفضل مرشّح للفيديو: og:video[:url|:secure_url] ثم twitter:player
        $videoElement = $xpath->query('(
            //meta[@property="og:video:secure_url"] |
            //meta[@property="og:video:url"] |
            //meta[@property="og:video"] |
            //meta[@name="twitter:player"]
        )[1]')->item(0);

        $imageUrl = ($imageElement instanceof \DOMElement) ? $imageElement->getAttribute('content') : null;
        $videoUrl = ($videoElement instanceof \DOMElement) ? $videoElement->getAttribute('content') : null;

        // تحويل الروابط النسبية إلى مطلقة اعتمادًا على عنوان الصفحة الأصلي
        $imageUrl = $imageUrl ? self::toAbsoluteUrl($imageUrl, $url) : null;
        $videoUrl = $videoUrl ? self::toAbsoluteUrl($videoUrl, $url) : null;

        // نوع الميديا
        $type = $videoUrl ? 'video' : ($imageUrl ? 'image' : 'link');

        return [
            'type'       => $type,
            'thumbnail'  => $imageUrl,
            'embed_html' => $videoUrl ? self::buildEmbedHtml($videoUrl) : null,
        ];
    }

    private static function fetchHtmlWithFallbacks(string $url): string
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9,ar;q=0.8',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
        ];

        $options = [
            'timeout' => 15,
            'connect_timeout' => 10,
            'allow_redirects' => true,
            'force_ip_resolve' => 'v4',
            'http_errors' => false,
        ];

        // محاولات يدوية بدون رمي استثناءات
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = Http::withHeaders($headers)
                ->withOptions($options)
                ->get($url);
            if ($response->ok()) {
                return $response->body();
            }
            usleep(500000 * ($attempt + 1)); // 0.5s, 1.0s, 1.5s
        }

        // محاولة خاصة لفيسبوك عبر نطاق الموبايل إذا كان الرابط من facebook.com
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        if (Str::contains($host, 'facebook.com')) {
            $mobileUrl = preg_replace('/^https?:\/\/(www\.)?facebook\.com/i', 'https://m.facebook.com', $url);
            $fbHeaders = array_merge($headers, [
                'Referer' => 'https://m.facebook.com/',
            ]);

            for ($attempt = 0; $attempt < 2; $attempt++) {
                $response = Http::withHeaders($fbHeaders)
                    ->withOptions($options)
                    ->get($mobileUrl);
                if ($response->ok()) {
                    return $response->body();
                }
                usleep(400000 * ($attempt + 1));
            }

            // محاولة عبر mbasic.facebook.com الأبسط في HTML
            $basicUrl = preg_replace('/^https?:\/\/(www\.)?facebook\.com/i', 'https://mbasic.facebook.com', $url);
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $response = Http::withHeaders($fbHeaders)
                    ->withOptions($options)
                    ->get($basicUrl);
                if ($response->ok()) {
                    return $response->body();
                }
                usleep(400000 * ($attempt + 1));
            }
        }

        // آخر محاولة بدون تحقق SSL (بيئات استضافة قد تفتقد شهادات CA)
        $sslOptions = array_merge($options, ['verify' => false]);
        for ($attempt = 0; $attempt < 1; $attempt++) {
            $response = Http::withHeaders($headers)
                ->withOptions($sslOptions)
                ->get($url);
            if ($response->ok()) {
                return $response->body();
            }
        }

        throw new \Exception('Failed to fetch URL (status ' . $response->status() . ')');
    }

    private static function normalizeUrl(string $rawUrl): string
    {
        $rawUrl = trim($rawUrl);
        // إزالة علامات اقتباس محيطة إن وُجدت (قد تأتي من curl --form)
        if ((Str::startsWith($rawUrl, '"') && Str::endsWith($rawUrl, '"')) || (Str::startsWith($rawUrl, "'") && Str::endsWith($rawUrl, "'"))) {
            $rawUrl = substr($rawUrl, 1, -1);
        }
        // إن كان الرابط يحتوي على ترميز بالمئة، نفك ترميزًا واحدًا فقط
        if (preg_match('/%[0-9A-Fa-f]{2}/', $rawUrl)) {
            $decoded = rawurldecode($rawUrl);
            // حماية من فك ترميز زائد يؤدي إلى كسر الرابط
            return $decoded ?: $rawUrl;
        }
        return $rawUrl;
    }

    private static function toAbsoluteUrl(string $candidateUrl, string $baseUrl): string
    {
        // إذا كان مطلقًا بالفعل
        if (preg_match('/^https?:\/\//i', $candidateUrl)) {
            return $candidateUrl;
        }

        // التعامل مع روابط البروتوكول النسبي مثل //example.com/img.jpg
        if (Str::startsWith($candidateUrl, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . $candidateUrl;
        }

        // تحويل مسارات نسبية إلى مطلقة
        $parsed = parse_url($baseUrl);
        if (!$parsed || empty($parsed['host'])) {
            return $candidateUrl;
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $basePath = isset($parsed['path']) ? rtrim(dirname($parsed['path']), '\\/') : '';

        if (Str::startsWith($candidateUrl, '/')) {
            // مسار يبدأ من الجذر
            return "$scheme://$host$port" . $candidateUrl;
        }

        // مسار نسبي على نفس المجلد
        return "$scheme://$host$port$basePath/" . $candidateUrl;
    }

    private static function buildEmbedHtml(string $src): string
    {
        // إطار تضمين بسيط مع سمات أمان/سماحيات معقولة
        $attributes = 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen';
        return "<iframe src=\"{$src}\" {$attributes}></iframe>";
    }
}
