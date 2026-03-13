<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Billing\PaymentTransaction;
use App\Models\Billing\Subscription;
use App\Models\Billing\SubscriptionEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * استقبال App Store Server Notifications V2 من Apple.
 * يُنصح بالتحقق من توقيع signedPayload باستخدام شهادة Apple في الإنتاج.
 */
class AppleNotificationController extends Controller
{
    public function receive(Request $request): JsonResponse
    {
        $signedPayload = $request->input('signedPayload');
        if (!$signedPayload || !is_string($signedPayload)) {
            Log::channel('single')->warning('[apple.notifications] Missing signedPayload');
            return response()->json(['message' => 'Bad Request'], 400);
        }

        try {
            $payload = $this->decodeJwsPayload($signedPayload);
            if (!is_array($payload)) {
                return response()->json(['message' => 'Invalid payload'], 400);
            }

            $notificationType = (string) ($payload['notificationType'] ?? '');
            $subtype = (string) ($payload['subtype'] ?? '');
            $data = $payload['data'] ?? [];

            $originalTransactionId = null;
            $expiresDate = null;
            if (!empty($data['signedTransactionInfo'])) {
                $txInfo = $this->decodeJwsPayload((string) $data['signedTransactionInfo']);
                if (is_array($txInfo)) {
                    $originalTransactionId = $txInfo['originalTransactionId'] ?? null;
                    $expiresDate = $txInfo['expiresDate'] ?? null;
                }
            }

            if (!$originalTransactionId) {
                Log::channel('single')->info('[apple.notifications] No originalTransactionId in payload', [
                    'notificationType' => $notificationType,
                ]);
                return response()->json(['message' => 'OK'], 200);
            }

            $transaction = PaymentTransaction::query()
                ->where('provider', 'apple')
                ->where('original_transaction_id', $originalTransactionId)
                ->orderByDesc('id')
                ->first();

            if (!$transaction || !$transaction->subscription_id) {
                Log::channel('single')->info('[apple.notifications] No subscription found for originalTransactionId', [
                    'originalTransactionId' => $originalTransactionId,
                ]);
                return response()->json(['message' => 'OK'], 200);
            }

            $subscription = Subscription::query()->find($transaction->subscription_id);
            if (!$subscription) {
                return response()->json(['message' => 'OK'], 200);
            }

            $this->applyNotification($subscription, $notificationType, $subtype, $expiresDate);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[apple.notifications] Error processing notification', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Error'], 500);
        }

        return response()->json(['message' => 'OK'], 200);
    }

    /**
     * فك ترميز الجزء payload من JWS (الجزء الأوسط base64url).
     */
    private function decodeJwsPayload(string $jws): ?array
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = $parts[1];
        $payload = str_replace(['-', '_'], ['+', '/'], $payload);
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }
        $data = json_decode($decoded, true);
        return is_array($data) ? $data : null;
    }

    private function applyNotification(Subscription $subscription, string $notificationType, string $subtype, ?string $expiresDate): void
    {
        $updates = [];
        $eventStatus = 'active';

        switch ($notificationType) {
            case 'DID_RENEW':
            case 'DID_CHANGE_RENEWAL_STATUS':
                $updates['auto_renew'] = true;
                if ($expiresDate !== null && $expiresDate !== '') {
                    try {
                        $updates['end_date'] = \Carbon\Carbon::parse($expiresDate);
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
                break;
            case 'CANCEL':
            case 'DID_FAIL_TO_RENEW':
                $updates['auto_renew'] = false;
                $eventStatus = $subtype === 'VOLUNTARY' ? 'cancelled' : 'past_due';
                $updates['status'] = $eventStatus;
                break;
            case 'EXPIRED':
                $updates['auto_renew'] = false;
                $updates['status'] = 'expired';
                $eventStatus = 'expired';
                break;
            default:
                Log::channel('single')->info('[apple.notifications] Unhandled notificationType', [
                    'notificationType' => $notificationType,
                    'subtype' => $subtype,
                ]);
                return;
        }

        if (!empty($updates)) {
            $subscription->update($updates);
        }

        SubscriptionEvent::query()->create([
            'subscription_id' => $subscription->id,
            'event_type' => $notificationType === 'DID_RENEW' ? 'renewed' : 'status_changed',
            'plan_id' => $subscription->plan_id,
            'status' => $eventStatus,
            'start_date' => $subscription->start_date,
            'end_date' => $subscription->end_date,
            'plan_price' => $subscription->price,
            'amount_charged' => null,
            'amount_refunded' => null,
            'currency' => $subscription->currency,
            'meta' => ['apple_notification_type' => $notificationType, 'subtype' => $subtype],
        ]);
    }
}
