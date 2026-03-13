<?php

namespace App\Http\Services\Billing;

use Illuminate\Support\Facades\Log;

/**
 * التحقق من توقيع JWS الخاص بـ App Store Server Notifications V2.
 * التوقيع يستخدم ES256 وشهادة x5c من Apple.
 */
class AppleJwsVerifier
{
    /**
     * التحقق من أن الـ JWS موقع من Apple (باستخدام الشهادة في الهيدر).
     */
    public function verify(string $jws): bool
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            return false;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;
        $header = $this->decodeBase64Url($headerB64);
        if ($header === null) {
            return false;
        }
        $headerJson = json_decode($header, true);
        if (!is_array($headerJson)) {
            return false;
        }

        $alg = (string) ($headerJson['alg'] ?? '');
        $x5c = $headerJson['x5c'] ?? null;
        if (!is_array($x5c) || empty($x5c)) {
            Log::channel('single')->warning('[apple.jws] Missing x5c in JWS header');
            return false;
        }

        $signingInput = $headerB64 . '.' . $payloadB64;
        $signatureBinary = $this->decodeBase64Url($signatureB64);
        if ($signatureBinary === null) {
            return false;
        }

        $certPem = $this->buildPemFromX5c($x5c[0]);
        if ($certPem === null) {
            return false;
        }

        $pubKey = openssl_pkey_get_public($certPem);
        if ($pubKey === false) {
            Log::channel('single')->warning('[apple.jws] Failed to get public key from certificate');
            return false;
        }

        $verified = false;
        if (strtoupper($alg) === 'ES256') {
            $signatureDer = $this->ecdsaRawToDer($signatureBinary);
            if ($signatureDer !== null) {
                $verified = openssl_verify(
                    $signingInput,
                    $signatureDer,
                    $pubKey,
                    OPENSSL_ALGO_SHA256
                ) === 1;
            }
        }

        openssl_pkey_free($pubKey);
        return $verified;
    }

    private function decodeBase64Url(string $input): ?string
    {
        $input = str_replace(['-', '_'], ['+', '/'], $input);
        $decoded = base64_decode($input, true);
        return $decoded !== false ? $decoded : null;
    }

    private function buildPemFromX5c(string $certBase64): ?string
    {
        $cert = base64_decode($certBase64, true);
        if ($cert === false) {
            return null;
        }
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split(base64_encode($cert), 64, "\n")
            . "-----END CERTIFICATE-----";
    }

    /**
     * تحويل توقيع ECDSA من صيغة raw (R||S) إلى DER لاستخدام openssl_verify.
     */
    private function ecdsaRawToDer(string $raw): ?string
    {
        $len = strlen($raw);
        if ($len !== 64 && $len !== 72) {
            return null;
        }
        $rLen = (int) ($len / 2);
        $r = substr($raw, 0, $rLen);
        $s = substr($raw, $rLen);

        $r = ltrim($r, "\0");
        $s = ltrim($s, "\0");
        if ($r === '' || $s === '') {
            return null;
        }
        if (ord($r[0]) & 0x80) {
            $r = "\0" . $r;
        }
        if (ord($s[0]) & 0x80) {
            $s = "\0" . $s;
        }
        $rDer = "\x02" . chr(strlen($r)) . $r;
        $sDer = "\x02" . chr(strlen($s)) . $s;
        $seq = $rDer . $sDer;
        $seqLen = strlen($seq);
        $lengthBytes = $seqLen > 127 ? "\x81" . chr($seqLen) : chr($seqLen);

        return "\x30" . $lengthBytes . $seq;
    }
}
