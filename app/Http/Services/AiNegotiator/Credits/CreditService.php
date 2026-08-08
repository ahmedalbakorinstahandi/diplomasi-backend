<?php

namespace App\Http\Services\AiNegotiator\Credits;

use App\Models\AiNegotiator\AiNegotiatorCreditTransaction;
use App\Models\AiNegotiator\AiNegotiatorSession;
use App\Models\AiNegotiator\AiNegotiatorUserCredit;
use App\Models\Users\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreditService
{
    public function __construct(
        protected CreditPolicy $policy,
    ) {}

    public function getOrCreateBalance(User $user): AiNegotiatorUserCredit
    {
        $balance = AiNegotiatorUserCredit::query()
            ->where('user_id', $user->id)
            ->first();

        if (!$balance) {
            $balance = AiNegotiatorUserCredit::create([
                'user_id' => $user->id,
                'credit_balance' => 0,
                'consumed_this_cycle' => 0,
                'cycle_started_at' => now(),
                'cycle_ends_at' => now()->addMonth()->startOfMonth(),
            ]);
        }

        return $this->refillIfDue($balance->fresh());
    }

    public function refillIfDue(AiNegotiatorUserCredit $balance): AiNegotiatorUserCredit
    {
        $due = $balance->last_refilled_at === null
            || ($balance->cycle_ends_at !== null && !now()->lt($balance->cycle_ends_at));

        if (!$due) {
            return $balance;
        }

        return DB::transaction(function () use ($balance) {
            /** @var AiNegotiatorUserCredit $locked */
            $locked = AiNegotiatorUserCredit::query()
                ->where('id', $balance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $stillDue = $locked->last_refilled_at === null
                || ($locked->cycle_ends_at !== null && !now()->lt($locked->cycle_ends_at));

            if (!$stillDue) {
                return $locked;
            }

            $user = User::query()->findOrFail($locked->user_id);
            $allotment = $this->policy->getMonthlyAllotment($user);
            $isInitial = $locked->last_refilled_at === null;

            $locked->credit_balance = $allotment;
            $locked->consumed_this_cycle = 0;
            $locked->cycle_started_at = now();
            $locked->cycle_ends_at = now()->addMonth();
            $locked->last_refilled_at = now();
            $locked->save();

            AiNegotiatorCreditTransaction::create([
                'user_id' => $locked->user_id,
                'ai_negotiator_session_id' => null,
                'type' => 'refill',
                'amount' => $allotment,
                'balance_after' => $allotment,
                'meta' => [
                    'access_mode' => $this->policy->accessMode(),
                    'reason' => $isInitial ? 'initial_allotment' : 'cycle_reset',
                ],
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function hasCredits(User $user): bool
    {
        $balance = $this->getOrCreateBalance($user);

        return $balance->credit_balance > 0;
    }

    public function consumeForSession(User $user, AiNegotiatorSession $session): void
    {
        DB::transaction(function () use ($user, $session) {
            $balance = AiNegotiatorUserCredit::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$balance) {
                $balance = $this->getOrCreateBalance($user);
                $balance = AiNegotiatorUserCredit::query()
                    ->where('id', $balance->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            } else {
                $balance = $this->refillIfDue($balance);
                $balance = AiNegotiatorUserCredit::query()
                    ->where('id', $balance->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($balance->credit_balance <= 0) {
                throw new RuntimeException('insufficient_credits');
            }

            $newBalance = $balance->credit_balance - 1;
            $balance->credit_balance = $newBalance;
            $balance->consumed_this_cycle = (int) $balance->consumed_this_cycle + 1;
            $balance->save();

            AiNegotiatorCreditTransaction::create([
                'user_id' => $user->id,
                'ai_negotiator_session_id' => $session->id,
                'type' => 'consume',
                'amount' => -1,
                'balance_after' => $newBalance,
                'meta' => [
                    'session_type' => $session->session_type,
                ],
                'created_at' => now(),
            ]);
        });
    }

    /**
     * @return array{balance: int, allotment: int, consumed_this_cycle: int, cycle_ends_at: \Illuminate\Support\Carbon|null, access_mode: string}
     */
    public function getCurrentBalance(User $user): array
    {
        $balance = $this->getOrCreateBalance($user);

        return [
            'balance' => (int) $balance->credit_balance,
            'allotment' => $this->policy->getMonthlyAllotment($user),
            'consumed_this_cycle' => (int) $balance->consumed_this_cycle,
            'cycle_ends_at' => $balance->cycle_ends_at,
            'access_mode' => $this->policy->accessMode(),
        ];
    }
}
