<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Deterministic backfill from historical Apple payment rows (first charge wins per original_transaction_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('apple_iap_subscription_ownerships') || !Schema::hasTable('payment_transactions')) {
            return;
        }

        $otids = DB::table('payment_transactions')
            ->where('provider', 'apple')
            ->whereNotNull('original_transaction_id')
            ->where('original_transaction_id', '!=', '')
            ->distinct()
            ->pluck('original_transaction_id');

        foreach ($otids as $otid) {
            $otid = (string) $otid;
            $first = DB::table('payment_transactions')
                ->where('provider', 'apple')
                ->where('original_transaction_id', $otid)
                ->orderBy('id')
                ->first();
            if (!$first || empty($first->user_id)) {
                continue;
            }

            $exists = DB::table('apple_iap_subscription_ownerships')
                ->where('original_transaction_id', $otid)
                ->exists();
            if ($exists) {
                continue;
            }

            $conflict = DB::table('payment_transactions')
                ->where('provider', 'apple')
                ->where('original_transaction_id', $otid)
                ->where('user_id', '!=', $first->user_id)
                ->exists();
            if ($conflict) {
                Log::channel('single')->warning('[apple.iap.backfill] Skipping conflicting original_transaction_id', [
                    'original_transaction_id' => $this->fingerprint($otid),
                ]);
                continue;
            }

            DB::table('apple_iap_subscription_ownerships')->insert([
                'user_id' => $first->user_id,
                'original_transaction_id' => $otid,
                'plan_id' => $first->plan_id,
                'product_id' => null,
                'environment' => null,
                'linked_at' => $first->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive: do not delete ownership rows that may have been updated in production.
    }

    private function fingerprint(string $otid): array
    {
        $len = strlen($otid);

        return [
            'otid_hash8' => substr(hash('sha256', $otid), 0, 8),
            'otid_len' => $len,
            'otid_prefix4' => $len >= 4 ? substr($otid, 0, 4) : $otid,
            'otid_suffix4' => $len > 8 ? substr($otid, -4) : null,
        ];
    }
};
