<?php

namespace App\Http\Services\Billing;

use App\Models\Billing\WebhookEvent;
use App\Support\MoyasarConfig;
use App\Services\MessageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MoyasarWebhookService
{
    public function __construct(
        protected MoyasarPaymentService $moyasarPaymentService
    ) {}

    /**
     * Persist the webhook payload and mark it processed.
     *
     * @return array{event: WebhookEvent, duplicate: bool}
     */
    public function ingest(array $payload): array
    {
        $eventType = (string) ($payload['type'] ?? '');
        $paymentId = $this->extractPaymentId($payload);
        Log::channel('single')->info('[billing.webhook] Received', [
            'event_type' => $eventType,
            'payment_id' => $paymentId,
            'payload_id' => $payload['id'] ?? null,
        ]);

        $this->validatePayload($payload);
        $this->validateSecretToken($payload['secret_token']);
        $isSupportedType = $this->isSupportedEventType($eventType);

        $event = WebhookEvent::firstOrCreate(
            [
                'provider' => 'moyasar',
                'payload_id' => (string) $payload['id'],
            ],
            [
                'event_type' => $payload['type'] ?? null,
                'event_created_at' => $this->parseDate($payload['created_at'] ?? null),
                'payment_id' => $this->extractPaymentId($payload),
                'secret_token_valid' => true,
                'payload' => $payload,
                'processing_status' => $isSupportedType ? 'received' : 'ignored',
            ]
        );

        if (!$event->wasRecentlyCreated) {
            Log::channel('single')->info('[billing.webhook] Duplicate event ignored', [
                'event_type' => $eventType,
                'payment_id' => $paymentId,
            ]);
            return [
                'event' => $event,
                'duplicate' => true,
            ];
        }

        // Keep webhook endpoint fast and deterministic.
        $event->update([
            'processing_status' => $isSupportedType ? 'processed' : 'ignored',
            'processed_at' => now(),
        ]);

        if ($isSupportedType && !empty($event->payment_id)) {
            $gatewayStatus = (string) ($payload['data']['status'] ?? $this->mapEventTypeToGatewayStatus((string) $payload['type']));
            Log::channel('single')->info('[billing.webhook] Processing', [
                'event_type' => $eventType,
                'payment_id' => $event->payment_id,
                'gateway_status' => $gatewayStatus,
            ]);
            if ((string) $payload['type'] === 'payment_refunded') {
                $this->moyasarPaymentService->processRefundWebhook($payload, $gatewayStatus);
            }
            $this->moyasarPaymentService->finalizeByGatewayPaymentId(
                (string) $event->payment_id,
                $gatewayStatus,
                $payload
            );
        } else {
            Log::channel('single')->info('[billing.webhook] Event ignored (unsupported or no payment_id)', [
                'event_type' => $eventType,
                'supported' => $isSupportedType,
            ]);
        }

        return [
            'event' => $event->fresh(),
            'duplicate' => false,
        ];
    }

    protected function validatePayload(array $payload): void
    {
        $requiredKeys = ['id', 'type', 'secret_token', 'data'];

        foreach ($requiredKeys as $requiredKey) {
            if (!array_key_exists($requiredKey, $payload)) {
                MessageService::abort(422, "Webhook payload missing required field: {$requiredKey}");
            }
        }

        if (!is_array($payload['data'])) {
            MessageService::abort(422, 'Webhook payload field data must be an object');
        }

        if (!is_string($payload['id']) || trim($payload['id']) === '') {
            MessageService::abort(422, 'Webhook payload field id must be a non-empty string');
        }

        if (!is_string($payload['type']) || trim($payload['type']) === '') {
            MessageService::abort(422, 'Webhook payload field type must be a non-empty string');
        }
    }

    protected function validateSecretToken(string $payloadSecret): void
    {
        $secrets = MoyasarConfig::webhookSecretsForVerification();

        if ($secrets === []) {
            MessageService::abort(500, 'Moyasar webhook secret token is not configured');
        }

        foreach ($secrets as $configuredSecret) {
            if (hash_equals($configuredSecret, $payloadSecret)) {
                return;
            }
        }

        MessageService::abort(401, 'Invalid webhook secret token');
    }

    protected function extractPaymentId(array $payload): ?string
    {
        $paymentId = $payload['data']['payment_id'] ?? null;
        if (!$paymentId) {
            $paymentId = $payload['data']['payment']['id'] ?? null;
        }
        if (!$paymentId) {
            $paymentId = $payload['data']['id'] ?? null;
        }
        if (!$paymentId) {
            return null;
        }

        return (string) $paymentId;
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $throwable) {
            return null;
        }
    }

    protected function isSupportedEventType(string $eventType): bool
    {
        $allowedEvents = (array) config('services.moyasar.webhook_events', []);

        if (empty($allowedEvents)) {
            return true;
        }

        return in_array($eventType, $allowedEvents, true);
    }

    protected function mapEventTypeToGatewayStatus(string $eventType): string
    {
        return match ($eventType) {
            'payment_paid' => 'paid',
            'payment_failed' => 'failed',
            'payment_voided' => 'voided',
            'payment_authorized' => 'authorized',
            'payment_captured' => 'captured',
            'payment_refunded' => 'refunded',
            'payment_verified' => 'verified',
            default => 'initiated',
        };
    }
}
