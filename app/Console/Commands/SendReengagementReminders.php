<?php

namespace App\Console\Commands;

use App\Http\Notifications\ReengagementNotification;
use App\Models\System\Notification;
use App\Models\System\ReengagementReminder;
use App\Models\System\Setting;
use App\Models\Users\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SendReengagementReminders extends Command
{
    protected $signature = 'users:send-reengagement-reminders {--window=10} {--limit=500}';

    protected $description = 'Send inactivity re-engagement reminders using flexible amount+unit rules.';

    public function handle(): int
    {
        $windowMinutes = max(1, (int) $this->option('window'));
        $limit = max(1, (int) $this->option('limit'));

        $this->bootstrapDefaults();

        if (!$this->isReminderEnabled()) {
            $this->info('Re-engagement reminders are disabled.');
            return self::SUCCESS;
        }

        $rules = $this->getRulesFromTable();
        if (empty($rules)) {
            $this->warn('No valid re-engagement rules found.');
            return self::SUCCESS;
        }

        $deepLink = $this->getNullableTextSetting('reengagement.cta_deep_link');
        $batchSize = min($limit, max(1, $this->getIntegerSetting('reengagement.batch_size', 500)));
        $now = now();
        $sent = 0;

        foreach ($rules as $rule) {
            if ($sent >= $limit) {
                break;
            }

            $rangeEnd = $this->subtractDuration($now->copy(), $rule['amount'], $rule['unit']);
            $rangeStart = $rangeEnd->copy()->subMinutes($windowMinutes);

            $users = User::query()
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->whereNotNull('last_opened_app_at')
                ->whereBetween('last_opened_app_at', [$rangeStart, $rangeEnd])
                ->whereDoesntHave('roles', function (Builder $query) {
                    $query->where('name', 'super_admin');
                })
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('personal_access_tokens')
                        ->whereColumn('personal_access_tokens.tokenable_id', 'users.id')
                        ->where('personal_access_tokens.tokenable_type', User::class)
                        ->whereNotNull('personal_access_tokens.device_token');
                })
                ->orderBy('id')
                ->limit($batchSize)
                ->get(['id', 'first_name', 'last_opened_app_at']);

            foreach ($users as $user) {
                if ($sent >= $limit) {
                    break 2;
                }

                $targetAt = $this->addDuration($user->last_opened_app_at->copy(), $rule['amount'], $rule['unit']);
                if ($now->lt($targetAt) || !$now->lt($targetAt->copy()->addMinutes($windowMinutes))) {
                    continue;
                }

                $ruleSignature = $rule['amount'] . ':' . $rule['unit'] . ':' . $rule['id'];
                $basisTimestamp = $user->last_opened_app_at->copy()->utc()->toIso8601String();

                if ($this->alreadySent($user->id, $ruleSignature, $basisTimestamp)) {
                    continue;
                }

                $title = $this->renderTemplate(
                    (string) $rule['title'],
                    (string) $user->first_name,
                    $rule['amount'],
                    $rule['unit']
                );
                $body = $this->renderTemplate(
                    (string) $rule['body'],
                    (string) $user->first_name,
                    $rule['amount'],
                    $rule['unit']
                );

                if ($title === '' || $body === '') {
                    continue;
                }

                ReengagementNotification::reminder(
                    userId: (int) $user->id,
                    title: $title,
                    body: $body,
                    amount: $rule['amount'],
                    unit: $rule['unit'],
                    ruleSignature: $ruleSignature,
                    basisTimestamp: $basisTimestamp,
                    deepLink: $deepLink
                );

                $sent++;
            }
        }

        $this->info('Re-engagement reminders sent: ' . $sent);

        return self::SUCCESS;
    }

    private function alreadySent(int $userId, string $ruleSignature, string $basisTimestamp): bool
    {
        return Notification::query()
            ->where('type', 'reengagement_reminder')
            ->where('user_id', $userId)
            ->where('data->rule_signature', $ruleSignature)
            ->where('data->basis_timestamp', $basisTimestamp)
            ->exists();
    }

    private function renderTemplate(string $text, string $firstName, int $amount, string $unit): string
    {
        $unitLabel = $this->unitLabel($amount, $unit);

        return trim((string) str_replace(
            ['{{first_name}}', '{{amount}}', '{{unit_label}}'],
            [$firstName, (string) $amount, $unitLabel],
            $text
        ));
    }

    private function unitLabel(int $amount, string $unit): string
    {
        if ($unit === 'day') {
            return $amount === 1 ? 'يوم' : 'أيام';
        }

        if ($unit === 'week') {
            return $amount === 1 ? 'أسبوع' : 'أسابيع';
        }

        if ($unit === 'month') {
            return $amount === 1 ? 'شهر' : 'أشهر';
        }

        return $amount === 1 ? 'سنة' : 'سنوات';
    }

    private function addDuration(Carbon $base, int $amount, string $unit): Carbon
    {
        return match ($unit) {
            'day' => $base->addDays($amount),
            'week' => $base->addWeeks($amount),
            'month' => $base->addMonthsNoOverflow($amount),
            'year' => $base->addYearsNoOverflow($amount),
            default => $base->addDays($amount),
        };
    }

    private function subtractDuration(Carbon $base, int $amount, string $unit): Carbon
    {
        return match ($unit) {
            'day' => $base->subDays($amount),
            'week' => $base->subWeeks($amount),
            'month' => $base->subMonthsNoOverflow($amount),
            'year' => $base->subYearsNoOverflow($amount),
            default => $base->subDays($amount),
        };
    }

    /**
     * @return array<int, array{id: int, amount: int, unit: string, title: string, body: string}>
     */
    private function getRulesFromTable(): array
    {
        $rows = ReengagementReminder::query()
            ->where('is_active', true)
            ->whereIn('unit', ReengagementReminder::UNITS)
            ->where('amount', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'amount', 'unit', 'title', 'body']);

        $rules = [];
        foreach ($rows as $row) {
            $title = trim((string) $row->title);
            $body = trim((string) $row->body);
            if ($title === '' || $body === '') {
                continue;
            }
            $rules[] = [
                'id' => (int) $row->id,
                'amount' => (int) $row->amount,
                'unit' => (string) $row->unit,
                'title' => $title,
                'body' => $body,
            ];
        }

        return $rules;
    }

    private function seedDefaultsIfEmpty(): void
    {
        if (ReengagementReminder::query()->exists()) {
            return;
        }

        $defaults = [
            ['amount' => 1, 'unit' => 'day', 'title' => 'مكانك لسا محجوز عندنا', 'body' => 'رجعتك اليوم بتعمل فرق كبير. افتح دبلوماسي وخذ خطوة صغيرة ترفع مستواك بسرعة.', 'sort_order' => 1],
            ['amount' => 3, 'unit' => 'day', 'title' => 'اشتقنالك في دبلوماسي', 'body' => 'ثلاث أيام غياب كفاية. ارجع الآن وكمل رحلتك من آخر نقطة وصلت لها.', 'sort_order' => 2],
            ['amount' => 7, 'unit' => 'day', 'title' => 'أسبوع بدونك كثير', 'body' => 'مهاراتك تستحق ترجع تتحرك. افتح التطبيق الآن وخلينا نكمل الإنجاز سوا.', 'sort_order' => 3],
            ['amount' => 14, 'unit' => 'day', 'title' => 'خلّي العودة تكون قوية', 'body' => 'مرّ وقت، لكن البداية دائمًا بإيدك. دقيقة واحدة الآن كفيلة تعيدك للمسار.', 'sort_order' => 4],
            ['amount' => 30, 'unit' => 'day', 'title' => 'رجعتك اليوم بداية جديدة', 'body' => 'شهر غياب وما زالت فرصتك كبيرة. ارجع اليوم وخذ دفعة جديدة نحو هدفك.', 'sort_order' => 5],
        ];

        foreach ($defaults as $item) {
            ReengagementReminder::query()->create($item);
        }
    }

    private function bootstrapDefaults(): void
    {
        $this->ensureSetting('reengagement.enabled', 'true', 'bool');
        $this->ensureSetting('reengagement.batch_size', '500', 'int');
        $this->ensureSetting('reengagement.cta_deep_link', '', 'text');
        $this->seedDefaultsIfEmpty();
    }

    private function ensureSetting(string $key, string $value, string $type): void
    {
        Setting::query()->firstOrCreate(
            ['key_name' => $key],
            [
                'value' => $value,
                'type' => $type,
                'is_settings' => true,
            ]
        );
    }

    private function isReminderEnabled(): bool
    {
        $raw = strtolower(trim($this->getRawSettingValue('reengagement.enabled', 'true')));
        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    private function getIntegerSetting(string $key, int $default): int
    {
        $raw = trim($this->getRawSettingValue($key, (string) $default));
        if (!ctype_digit($raw)) {
            return $default;
        }

        return max(1, (int) $raw);
    }

    private function getNullableTextSetting(string $key): ?string
    {
        $raw = trim($this->getRawSettingValue($key, ''));
        return $raw === '' ? null : $raw;
    }

    private function getRawSettingValue(string $key, string $default): string
    {
        $setting = Setting::query()
            ->where('key_name', $key)
            ->whereNull('deleted_at')
            ->first();

        if (!$setting) {
            return $default;
        }

        $raw = $setting->getRawOriginal('value');
        if ($raw === null) {
            return $default;
        }

        return (string) $raw;
    }
}
