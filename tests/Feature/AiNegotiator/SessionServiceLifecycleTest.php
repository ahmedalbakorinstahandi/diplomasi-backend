<?php

namespace Tests\Feature\AiNegotiator;

use App\Http\Services\AiNegotiator\Credits\CreditService;
use App\Http\Services\AiNegotiator\SessionService;
use App\Models\AiNegotiator\AiNegotiatorCreditTransaction;
use App\Models\AiNegotiator\AiNegotiatorEvaluation;
use App\Models\AiNegotiator\AiNegotiatorEvaluationScore;
use App\Models\AiNegotiator\AiNegotiatorMessage;
use App\Models\AiNegotiator\AiNegotiatorRubricItem;
use App\Models\AiNegotiator\AiNegotiatorSession;
use App\Models\AiNegotiator\AiNegotiatorUserCredit;
use App\Models\System\Setting;
use App\Models\Users\User;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderInterface;
use App\Services\AiNegotiator\Llm\Contracts\LlmResponse;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SessionServiceLifecycleTest extends TestCase
{
    private SessionService $sessions;

    private CreditService $credits;

    private User $user;

    /** @var list<LlmResponse> */
    private array $llmQueue = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->app['db']->purge('sqlite');
        $this->app['db']->reconnect('sqlite');

        $this->createSchema();
        $this->seedSettingsAndRubric();

        $this->user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'ain-test-' . uniqid() . '@example.com',
            'phone' => '05' . random_int(10000000, 99999999),
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $mock = Mockery::mock(LlmProviderInterface::class);
        $mock->shouldReceive('name')->andReturn('claude');
        $mock->shouldReceive('chat')->andReturnUsing(function () {
            if ($this->llmQueue === []) {
                throw new RuntimeException('llm_queue_empty');
            }

            return array_shift($this->llmQueue);
        });

        $this->app->instance(LlmProviderInterface::class, $mock);

        $this->sessions = app(SessionService::class);
        $this->credits = app(CreditService::class);
    }

    public function test_full_lifecycle_credits_and_state_machine(): void
    {
        // 1) Balance auto-created with 3 free credits
        $balanceInfo = $this->credits->getCurrentBalance($this->user);
        $this->assertSame(3, $balanceInfo['balance']);
        $this->assertSame(3, $balanceInfo['allotment']);
        $this->assertDatabaseCount('ai_negotiator_user_credits', 1);

        // 8) Mid-way active session conflict covered after start
        // 2) Start session
        $session = $this->sessions->startSession($this->user);
        $this->assertSame('intake', $session->session_state);
        $this->assertSame(3, $this->credits->getCurrentBalance($this->user)['balance']);

        try {
            $this->sessions->startSession($this->user);
            $this->fail('Expected active_session_exists');
        } catch (RuntimeException $e) {
            $this->assertSame('active_session_exists', $e->getMessage());
        }

        // 3) Intake message (no complete)
        $this->pushLlm('ما هدفك من هذا التفاوض؟');
        $r1 = $this->sessions->submitIntakeMessage($session, 'أريد رفع راتبي');
        $this->assertFalse($r1['intake_complete']);
        $this->assertSame('intake', $r1['session_state']);
        $this->assertInstanceOf(AiNegotiatorMessage::class, $r1['user_message']);
        $this->assertInstanceOf(AiNegotiatorMessage::class, $r1['assistant_message']);
        $this->assertSame('أريد رفع راتبي', $r1['user_message']->content);
        $this->assertSame('ما هدفك من هذا التفاوض؟', $r1['assistant_message']->content);
        $this->assertGreaterThan(0, $r1['user_message']->id);
        $this->assertGreaterThan(0, $r1['assistant_message']->id);
        $this->assertLessThan($r1['assistant_message']->order_index, $r1['user_message']->order_index);
        $this->assertSame(3, $this->credits->getCurrentBalance($this->user)['balance']);
        $this->assertSame(2, AiNegotiatorMessage::where('ai_negotiator_session_id', $session->id)->count());

        // 4) Intake complete → simulating + credit consume + persona
        $this->pushLlm("شكراً، اكتملت التهيئة.\n<INTAKE_COMPLETE>");
        $this->pushLlm(json_encode($this->samplePersona(), JSON_UNESCAPED_UNICODE));

        $r2 = $this->sessions->submitIntakeMessage($session->fresh(), 'الحد الأدنى زيادة 10%');
        $this->assertTrue($r2['intake_complete']);
        $this->assertSame('simulating', $r2['session_state']);
        $this->assertNotNull($r2['opponent_persona']);
        $this->assertSame('سعد', $r2['opponent_persona']['name']);
        $this->assertSame(2, $this->credits->getCurrentBalance($this->user)['balance']);

        $consume = AiNegotiatorCreditTransaction::query()
            ->where('type', 'consume')
            ->where('ai_negotiator_session_id', $session->id)
            ->first();
        $this->assertNotNull($consume);
        $this->assertSame(-1, (int) $consume->amount);

        $session = $session->fresh();
        $this->assertSame('simulating', $session->session_state);
        $this->assertNotNull($session->simulating_started_at);
        $this->assertNotNull($session->opponent_persona);

        // 5) Simulation message
        $this->pushLlm('هذا الطلب مرتفع في ظل ميزانيتنا الحالية.');
        $r3 = $this->sessions->submitSimulationMessage($session, 'أريد زيادة 20% هذا الشهر');
        $this->assertSame('simulating', $r3['session_state']);
        $this->assertNull($r3['evaluation']);
        $this->assertInstanceOf(AiNegotiatorMessage::class, $r3['user_message']);
        $this->assertInstanceOf(AiNegotiatorMessage::class, $r3['assistant_message']);
        $this->assertSame('أريد زيادة 20% هذا الشهر', $r3['user_message']->content);
        $this->assertStringContainsString('ميزانيتنا', $r3['assistant_message']->content);

        // 6) End simulation → evaluation → completed
        $this->pushLlm(json_encode($this->sampleEvaluation(), JSON_UNESCAPED_UNICODE));
        $evaluation = $this->sessions->endSimulation($session->fresh());

        $this->assertInstanceOf(AiNegotiatorEvaluation::class, $evaluation);
        $this->assertSame(72, (int) $evaluation->overall_score);
        $this->assertCount(8, $evaluation->scores);
        $this->assertSame(8, AiNegotiatorEvaluationScore::where('ai_negotiator_evaluation_id', $evaluation->id)->count());

        $session = $session->fresh();
        $this->assertSame('completed', $session->session_state);
        $this->assertNotNull($session->completed_at);

        // 7) New session after terminal succeeds
        $next = $this->sessions->startSession($this->user);
        $this->assertSame('intake', $next->session_state);
        $this->assertNotSame($session->id, $next->id);
    }

    private function pushLlm(string $content): void
    {
        $this->llmQueue[] = new LlmResponse(
            content: $content,
            inputTokens: 10,
            outputTokens: 20,
            model: 'claude-sonnet-4-6',
            stopReason: 'end_turn',
            raw: [],
        );
    }

    /**
     * @return array<string, string>
     */
    private function samplePersona(): array
    {
        return [
            'name' => 'سعد',
            'role' => 'مدير مباشر',
            'title' => 'مدير القسم',
            'tone' => 'صارم',
            'apparent_goal' => 'تأجيل الزيادة',
            'hidden_goal' => 'الحفاظ على ميزانية القسم',
            'acceptable_limit' => 'زيادة 5% كحد أقصى',
            'pressure_points' => 'مقارنة السوق',
            'objection_style' => 'أسئلة عن الأرقام',
            'what_makes_soften' => 'خطة أداء واضحة',
            'what_makes_harden' => 'مقارنات عاطفية',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleEvaluation(): array
    {
        return [
            'overall_score' => 72,
            'summary' => 'أداء جيد مع حاجة لتحسين الأسئلة.',
            'best_line' => 'أريد زيادة مبنية على إنجازاتي.',
            'weakest_line' => 'وإلا سأستقيل.',
            'biggest_mistake' => 'تهديد مبكر.',
            'quick_concession' => false,
            'sensitive_info_leaked' => false,
            'good_questions' => true,
            'suggested_alternative_response' => 'أقترح ربط الزيادة بمؤشرات أداء.',
            'retry_exercise' => 'أعد الافتتاحية دون تهديد.',
            'suggested_next_difficulty' => 'realistic',
            'rubric_scores' => [
                ['code' => 'goal_clarity', 'score' => 8],
                ['code' => 'opening_strength', 'score' => 7],
                ['code' => 'questioning', 'score' => 10],
                ['code' => 'interest_understanding', 'score' => 12],
                ['code' => 'objection_handling', 'score' => 11],
                ['code' => 'no_quick_concession', 'score' => 12],
                ['code' => 'calm_assertiveness', 'score' => 6],
                ['code' => 'relationship_building', 'score' => 6],
            ],
        ];
    }

    private function seedSettingsAndRubric(): void
    {
        $settings = [
            ['key_name' => 'ai_negotiator.access_mode', 'value' => 'credits_based', 'type' => 'text'],
            ['key_name' => 'ai_negotiator.free_credits_monthly', 'value' => '3', 'type' => 'int'],
            ['key_name' => 'ai_negotiator.paid_credits_monthly', 'value' => '30', 'type' => 'int'],
            ['key_name' => 'ai_negotiator.max_messages_per_session', 'value' => '40', 'type' => 'int'],
        ];

        foreach ($settings as $row) {
            Setting::create([
                'key_name' => $row['key_name'],
                'value' => $row['value'],
                'type' => $row['type'],
                'is_settings' => true,
            ]);
        }

        $items = [
            ['goal_clarity', 10, 1],
            ['opening_strength', 10, 2],
            ['questioning', 15, 3],
            ['interest_understanding', 15, 4],
            ['objection_handling', 15, 5],
            ['no_quick_concession', 15, 6],
            ['calm_assertiveness', 10, 7],
            ['relationship_building', 10, 8],
        ];

        foreach ($items as [$code, $weight, $order]) {
            AiNegotiatorRubricItem::create([
                'code' => $code,
                'title' => $code,
                'description' => $code,
                'weight' => $weight,
                'order_index' => $order,
                'is_published' => true,
            ]);
        }
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('password', 255)->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_guest')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('status');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key_name', 100);
            $table->text('value')->nullable();
            $table->string('type');
            $table->boolean('is_settings')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_negotiator_rubric_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('weight');
            $table->unsignedBigInteger('order_index')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_negotiator_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('session_type')->default('practice');
            $table->string('session_state')->default('intake');
            $table->string('difficulty')->default('realistic');
            $table->string('training_mode')->default('realistic');
            $table->string('situation_type', 50)->nullable();
            $table->json('intake_data')->nullable();
            $table->json('opponent_persona')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('simulating_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_negotiator_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_negotiator_session_id');
            $table->string('role');
            $table->string('type')->default('text');
            $table->text('content');
            $table->unsignedInteger('tokens_used')->nullable();
            $table->string('state_at_time');
            $table->unsignedBigInteger('order_index');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_negotiator_session_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_negotiator_session_id');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ai_negotiator_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_negotiator_session_id');
            $table->unsignedTinyInteger('overall_score');
            $table->text('summary');
            $table->text('best_line')->nullable();
            $table->text('weakest_line')->nullable();
            $table->text('biggest_mistake')->nullable();
            $table->boolean('quick_concession')->default(false);
            $table->boolean('sensitive_info_leaked')->default(false);
            $table->boolean('good_questions')->default(false);
            $table->text('suggested_alternative_response')->nullable();
            $table->text('retry_exercise')->nullable();
            $table->string('suggested_next_difficulty')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_negotiator_evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ai_negotiator_evaluation_id');
            $table->unsignedBigInteger('ai_negotiator_rubric_item_id');
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('max_score');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_negotiator_user_credits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('credit_balance')->default(0);
            $table->unsignedInteger('consumed_this_cycle')->default(0);
            $table->dateTime('cycle_started_at');
            $table->dateTime('cycle_ends_at');
            $table->timestamp('last_refilled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_negotiator_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('ai_negotiator_session_id')->nullable();
            $table->string('type');
            $table->integer('amount');
            $table->unsignedInteger('balance_after');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
