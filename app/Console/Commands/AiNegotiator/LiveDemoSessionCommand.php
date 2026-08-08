<?php

namespace App\Console\Commands\AiNegotiator;

use App\Http\Services\AiNegotiator\Credits\CreditService;
use App\Http\Services\AiNegotiator\SessionService;
use App\Models\AiNegotiator\AiNegotiatorRubricItem;
use App\Models\AiNegotiator\AiNegotiatorSession;
use App\Models\System\Setting;
use App\Models\Users\User;
use Database\Seeders\AiNegotiatorRubricItemSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Throwable;

class LiveDemoSessionCommand extends Command
{
    protected $signature = 'ai-negotiator:live-demo
                            {--email=ain-live-demo@diplomasi.local : Demo user email}
                            {--max-intake=8 : Max intake turns}
                            {--sim-turns=3 : Simulation user turns before end}';

    protected $description = 'Run a full live AI Negotiator practice session against Claude and write a JSON report.';

    public function handle(SessionService $sessions, CreditService $credits): int
    {
        $apiKey = (string) config('services.ai_negotiator.claude.api_key', '');
        if (trim($apiKey) === '') {
            $this->error('Missing AI_NEGOTIATOR_CLAUDE_API_KEY. Run: php artisan config:clear');

            return self::FAILURE;
        }

        $this->ensureSettingsAndRubric();

        $user = $this->resolveUser((string) $this->option('email'));
        $this->abandonActive($sessions, $user);

        $balanceBefore = $credits->getCurrentBalance($user);
        $this->info("User #{$user->id} · balance {$balanceBefore['balance']} · mode {$balanceBefore['access_mode']}");

        $startedAt = microtime(true);
        $transcript = [];
        $reportPath = storage_path('app/ai-negotiator-live-demo.json');

        try {
            $session = $sessions->startSession($user, 'practice', 'realistic', 'realistic', 'salary_raise');
            $this->line("Session #{$session->id} started → intake");

            $intakeAnswers = [
                'أريد التفاوض على زيادة راتب مع مديري المباشر في شركة تقنية متوسطة.',
                'هدفي الحصول على زيادة 15٪ مع الحفاظ على علاقة جيدة معه.',
                'الحد الأدنى المقبول لدي هو 8٪، وأفضّل عدم التهديد بالاستقالة.',
                'لدي عرض سوق تقريبي أعلى بـ 12٪، لكنني أفضّل البقاء إذا تحسنت الشروط.',
                'أقلق من رد فعله بشأن ميزانية القسم وضغط نهاية السنة المالية.',
                'أفضل أسلوباً هادئاً مهنياً يعتمد على الإنجازات والأرقام.',
                'المدة المتوقعة للتفاوض جلسة واحدة قصيرة هذا الأسبوع.',
                'لا أريد كشف تفاصيل عرض خارجي بالكامل في البداية.',
            ];

            $maxIntake = max(1, (int) $this->option('max-intake'));
            $intakeComplete = false;

            for ($i = 0; $i < $maxIntake; $i++) {
                $userMsg = $intakeAnswers[$i] ?? 'هذه كل التفاصيل الأساسية لدي حالياً.';
                $this->comment("[intake {$i}] USER: {$userMsg}");

                $result = $sessions->submitIntakeMessage($session->fresh(), $userMsg);
                $transcript[] = [
                    'phase' => 'intake',
                    'user' => $userMsg,
                    'assistant' => $result['assistant_message'],
                    'intake_complete' => $result['intake_complete'],
                    'session_state' => $result['session_state'],
                ];

                $this->line('[intake] ASSISTANT: ' . mb_substr($result['assistant_message'], 0, 180) . '…');

                if ($result['intake_complete']) {
                    $intakeComplete = true;
                    $session = $session->fresh();
                    $this->info("Intake complete → simulating · persona: " . ($session->opponent_persona['name'] ?? '?'));
                    break;
                }
            }

            if (!$intakeComplete) {
                throw new \RuntimeException('intake_did_not_complete_within_max_turns');
            }

            $simTurns = [
                'شكراً لوقتكم. بناءً على إنجازاتي هذا العام ونمو مسؤولياتي، أود مناقشة تعديل الراتب بنسبة 15٪.',
                'أفهم قيود الميزانية. هل يمكننا الاتفاق على زيادة جزئية الآن وربط الجزء المتبقي بمؤشرات أداء واضحة خلال ستة أشهر؟',
                'ما المعلومات أو المؤشرات التي تحتاجها مني لتدعم هذا الطلب داخل الإدارة؟',
            ];

            $simCount = min(count($simTurns), max(1, (int) $this->option('sim-turns')));
            for ($i = 0; $i < $simCount; $i++) {
                $userMsg = $simTurns[$i];
                $this->comment("[sim {$i}] USER: {$userMsg}");

                $result = $sessions->submitSimulationMessage($session->fresh(), $userMsg);
                $transcript[] = [
                    'phase' => 'simulating',
                    'user' => $userMsg,
                    'assistant' => $result['assistant_message'],
                    'session_state' => $result['session_state'],
                ];

                $this->line('[sim] ASSISTANT: ' . mb_substr($result['assistant_message'], 0, 180) . '…');
                $session = $session->fresh();

                if ($result['evaluation'] !== null) {
                    $this->warn('Message cap reached — evaluation already generated.');
                    break;
                }
            }

            $evaluation = null;
            if ($session->fresh()->session_state === 'simulating') {
                $this->comment('Ending simulation → evaluating…');
                $evaluation = $sessions->endSimulation($session->fresh());
            } else {
                $evaluation = $session->fresh()->evaluation()?->load('scores.rubricItem');
            }

            $session = $session->fresh(['messages', 'evaluation.scores.rubricItem', 'events']);
            $balanceAfter = $credits->getCurrentBalance($user);
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

            $scores = [];
            if ($evaluation) {
                foreach ($evaluation->scores as $score) {
                    $scores[] = [
                        'code' => $score->rubricItem?->code,
                        'title' => $score->rubricItem?->title,
                        'score' => (int) $score->score,
                        'max_score' => (int) $score->max_score,
                    ];
                }
            }

            $report = [
                'ok' => true,
                'ran_at' => now()->toIso8601String(),
                'elapsed_ms' => $elapsedMs,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
                'credits' => [
                    'before' => $balanceBefore,
                    'after' => $balanceAfter,
                ],
                'session' => [
                    'id' => $session->id,
                    'session_state' => $session->session_state,
                    'difficulty' => $session->difficulty,
                    'situation_type' => $session->situation_type,
                    'started_at' => optional($session->started_at)?->toIso8601String(),
                    'simulating_started_at' => optional($session->simulating_started_at)?->toIso8601String(),
                    'completed_at' => optional($session->completed_at)?->toIso8601String(),
                    'opponent_persona' => $session->opponent_persona,
                    'message_count' => $session->messages->count(),
                    'event_count' => $session->events->count(),
                ],
                'evaluation' => $evaluation ? [
                    'id' => $evaluation->id,
                    'overall_score' => (int) $evaluation->overall_score,
                    'summary' => $evaluation->summary,
                    'best_line' => $evaluation->best_line,
                    'weakest_line' => $evaluation->weakest_line,
                    'biggest_mistake' => $evaluation->biggest_mistake,
                    'quick_concession' => (bool) $evaluation->quick_concession,
                    'sensitive_info_leaked' => (bool) $evaluation->sensitive_info_leaked,
                    'good_questions' => (bool) $evaluation->good_questions,
                    'suggested_alternative_response' => $evaluation->suggested_alternative_response,
                    'retry_exercise' => $evaluation->retry_exercise,
                    'suggested_next_difficulty' => $evaluation->suggested_next_difficulty,
                    'scores' => $scores,
                ] : null,
                'transcript' => $transcript,
            ];

            file_put_contents($reportPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->newLine();
            $this->info("DONE · state={$session->session_state} · overall=" . ($evaluation->overall_score ?? 'n/a'));
            $this->info("Report: {$reportPath}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $report = [
                'ok' => false,
                'ran_at' => now()->toIso8601String(),
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'transcript' => $transcript,
            ];
            file_put_contents($reportPath, json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->error($e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    private function resolveUser(string $email): User
    {
        $user = User::query()->where('email', $email)->first();
        if ($user) {
            return $user;
        }

        return User::create([
            'first_name' => 'AI',
            'last_name' => 'LiveDemo',
            'email' => $email,
            'phone' => '0599' . random_int(100000, 999999),
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'email_verified' => true,
            'is_guest' => false,
            'is_active' => true,
        ]);
    }

    private function abandonActive(SessionService $sessions, User $user): void
    {
        $active = $sessions->getActiveSession($user);
        if ($active) {
            $sessions->abandonSession($active);
            $this->warn("Abandoned previous active session #{$active->id}");
        }
    }

    private function ensureSettingsAndRubric(): void
    {
        $defaults = [
            ['key_name' => 'ai_negotiator.access_mode', 'value' => 'credits_based', 'type' => 'text'],
            ['key_name' => 'ai_negotiator.free_credits_monthly', 'value' => '3', 'type' => 'int'],
            ['key_name' => 'ai_negotiator.paid_credits_monthly', 'value' => '30', 'type' => 'int'],
            ['key_name' => 'ai_negotiator.max_messages_per_session', 'value' => '40', 'type' => 'int'],
            ['key_name' => 'ai_negotiator.llm_provider', 'value' => 'claude', 'type' => 'text'],
            ['key_name' => 'ai_negotiator.llm_model', 'value' => 'claude-sonnet-4-6', 'type' => 'text'],
        ];

        foreach ($defaults as $row) {
            Setting::query()->firstOrCreate(
                ['key_name' => $row['key_name']],
                [
                    'value' => $row['value'],
                    'type' => $row['type'],
                    'is_settings' => true,
                ]
            );
        }

        if (AiNegotiatorRubricItem::query()->count() === 0) {
            $this->call('db:seed', ['--class' => AiNegotiatorRubricItemSeeder::class, '--force' => true]);
        }
    }
}
