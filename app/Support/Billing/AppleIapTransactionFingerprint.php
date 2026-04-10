<?php

namespace App\Support\Billing;

/**
 * Safe logging for Apple original_transaction_id (never log full receipt).
 */
final class AppleIapTransactionFingerprint
{
    public static function forLog(string $originalTransactionId): array
    {
        $len = strlen($originalTransactionId);

        return [
            'otid_hash8' => substr(hash('sha256', $originalTransactionId), 0, 8),
            'otid_len' => $len,
            'otid_prefix4' => $len >= 4 ? substr($originalTransactionId, 0, 4) : $originalTransactionId,
            'otid_suffix4' => $len > 8 ? substr($originalTransactionId, -4) : null,
        ];
    }
}
