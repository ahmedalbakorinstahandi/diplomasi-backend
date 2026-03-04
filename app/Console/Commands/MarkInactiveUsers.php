<?php

namespace App\Console\Commands;

use App\Models\Users\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkInactiveUsers extends Command
{
    protected $signature = 'users:mark-inactive {--minutes=2} {--limit=1000}';

    protected $description = 'Mark users inactive when they stop hitting APIs for a period.';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, (int) $this->option('limit'));
        $threshold = now()->subMinutes($minutes);

        $users = User::query()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<=', $threshold)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'last_activity_at', 'inactive_since_at']);

        $updated = 0;

        foreach ($users as $user) {
            $inactiveSinceAt = $user->last_activity_at?->copy()->addMinutes($minutes) ?? now();

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'is_active' => false,
                    'inactive_since_at' => $user->inactive_since_at ?? $inactiveSinceAt,
                    'updated_at' => now(),
                ]);

            $updated++;
        }

        $this->info('Users marked inactive: ' . $updated);

        return self::SUCCESS;
    }
}
