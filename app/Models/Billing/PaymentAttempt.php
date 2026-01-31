<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PaymentAttempt extends Model
{
    use HasFactory;

    protected $table = 'payment_attempts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'plan_id',
        'type',
        'merchant_reference',
        'geidea_session_id',
        'geidea_order_id',
        'checkout_url',
        'token_id',
        'amount',
        'currency',
        'status',
        'failure_reason',
        'subscription_id',
        'verified_at',
        'expires_at',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the payment attempt.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\Users\User::class);
    }

    /**
     * Get the plan for this payment attempt.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the subscription created from this payment attempt.
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Scope a query to only include attempts by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include attempts by merchant reference.
     */
    public function scopeByMerchantReference($query, string $merchantReference)
    {
        return $query->where('merchant_reference', $merchantReference);
    }

    /**
     * Scope a query to only include pending attempts.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include verifying attempts.
     */
    public function scopeVerifying($query)
    {
        return $query->where('status', 'verifying');
    }

    /**
     * Scope a query to only include completed attempts.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include failed attempts.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include verified attempts.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Mark the payment attempt as completed.
     */
    public function markCompleted(?int $subscriptionId = null): void
    {
        $this->update([
            'status' => 'completed',
            'subscription_id' => $subscriptionId ?? $this->subscription_id,
            'verified_at' => now(),
        ]);
    }

    /**
     * Mark the payment attempt as failed.
     */
    public function markFailed(?string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'verified_at' => now(),
        ]);
    }

    /**
     * Mark the payment attempt as verifying.
     */
    public function markVerifying(): void
    {
        $this->update([
            'status' => 'verifying',
        ]);
    }

    /**
     * Mark the payment attempt as verified (sets verified_at timestamp).
     */
    public function markVerified(): void
    {
        $this->update([
            'verified_at' => now(),
        ]);
    }

    /**
     * Check if the payment attempt is expired.
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return Carbon::now()->isAfter($this->expires_at);
    }

    /**
     * Check if the payment attempt is verified.
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Check if the payment attempt can be verified (old enough and still pending).
     */
    public function canBeVerified(int $minAgeSeconds = 60): bool
    {
        if ($this->status !== 'pending' && $this->status !== 'verifying') {
            return false;
        }

        if ($this->isVerified()) {
            return false;
        }

        $ageInSeconds = Carbon::now()->diffInSeconds($this->created_at);
        return $ageInSeconds >= $minAgeSeconds;
    }
}
