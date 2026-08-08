<?php

namespace Tests\Feature\AiNegotiator;

use App\Models\AiNegotiator\AiNegotiatorRubricItem;
use App\Models\AiNegotiator\AiNegotiatorUserCredit;
use App\Models\System\Setting;
use App\Models\Users\Permission;
use App\Models\Users\Role;
use App\Models\Users\RolePermission;
use App\Models\Users\User;
use App\Models\Users\UserRole;
use App\Services\AiNegotiator\Llm\Contracts\LlmProviderInterface;
use App\Services\AiNegotiator\Llm\Contracts\LlmResponse;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AiNegotiatorHttpTest extends TestCase
{
    private User $user;

    private string $token;

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
        $this->seedPermissions();

        $this->user = $this->makeVerifiedUser('ain-http-' . uniqid() . '@example.com');
        $this->token = $this->user->createToken('test')->plainTextToken;

        $mock = Mockery::mock(LlmProviderInterface::class);
        $mock->shouldReceive('name')->andReturn('claude');
        $mock->shouldReceive('chat')->andReturnUsing(function () {
            if ($this->llmQueue === []) {
                throw new RuntimeException('llm_queue_empty');
            }

            return array_shift($this->llmQueue);
        });
        $this->app->instance(LlmProviderInterface::class, $mock);
    }

    public function test_start_session_returns_intake(): void
    {
        $response = $this->authPost('/api/v1/user/ai-negotiator/sessions', []);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.session_state', 'intake');
    }

    public function test_second_active_session_returns_409(): void
    {
        $this->authPost('/api/v1/user/ai-negotiator/sessions', [])->assertStatus(200);

        $this->authPost('/api/v1/user/ai-negotiator/sessions', [])
            ->assertStatus(409)
            ->assertJsonPath('key', 'messages.ai_negotiator.session_already_active');
    }

    public function test_unverified_user_gets_403(): void
    {
        $unverified = $this->makeVerifiedUser('ain-unverified-' . uniqid() . '@example.com', verified: false);
        $token = $unverified->createToken('test')->plainTextToken;

        $this->resetAuthState();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Context' => 'app',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/user/ai-negotiator/sessions', [])
            ->assertStatus(403);
    }

    public function test_zero_credits_returns_402(): void
    {
        AiNegotiatorUserCredit::create([
            'user_id' => $this->user->id,
            'credit_balance' => 0,
            'consumed_this_cycle' => 0,
            'cycle_started_at' => now(),
            'cycle_ends_at' => now()->addMonth(),
            'last_refilled_at' => now(),
        ]);

        $this->authPost('/api/v1/user/ai-negotiator/sessions', [])
            ->assertStatus(402)
            ->assertJsonPath('key', 'messages.ai_negotiator.insufficient_credits');
    }

    public function test_intake_message_returns_assistant_reply(): void
    {
        $sessionId = $this->authPost('/api/v1/user/ai-negotiator/sessions', [])
            ->json('data.id');

        $this->pushLlm('ما هدفك من هذا التفاوض؟');

        $response = $this->authPost("/api/v1/user/ai-negotiator/sessions/{$sessionId}/messages", [
            'content' => 'أريد رفع راتبي',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.assistant_message.content', 'ما هدفك من هذا التفاوض؟')
            ->assertJsonPath('data.assistant_message.role', 'assistant')
            ->assertJsonPath('data.user_message.content', 'أريد رفع راتبي')
            ->assertJsonPath('data.user_message.role', 'user')
            ->assertJsonPath('data.session_state', 'intake')
            ->assertJsonPath('data.intake_complete', false);

        $this->assertGreaterThan(0, $response->json('data.user_message.id'));
        $this->assertGreaterThan(0, $response->json('data.assistant_message.id'));
        $this->assertLessThan(
            $response->json('data.assistant_message.order_index'),
            $response->json('data.user_message.order_index')
        );
    }

    public function test_intake_complete_transitions_to_simulating(): void
    {
        $sessionId = $this->authPost('/api/v1/user/ai-negotiator/sessions', [])
            ->json('data.id');

        $this->pushLlm('ما هدفك؟');
        $this->authPost("/api/v1/user/ai-negotiator/sessions/{$sessionId}/messages", [
            'content' => 'رفع الراتب',
        ])->assertStatus(200);

        $this->pushLlm("اكتملت التهيئة\n<INTAKE_COMPLETE>");
        $this->pushLlm(json_encode($this->samplePersona(), JSON_UNESCAPED_UNICODE));

        $this->authPost("/api/v1/user/ai-negotiator/sessions/{$sessionId}/messages", [
            'content' => 'الحد الأدنى 10%',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.intake_complete', true)
            ->assertJsonPath('data.session_state', 'simulating');
    }

    public function test_end_produces_evaluation_with_eight_scores(): void
    {
        $sessionId = $this->reachSimulating();

        $this->pushLlm('رد الخصم.');
        $this->authPost("/api/v1/user/ai-negotiator/sessions/{$sessionId}/messages", [
            'content' => 'أريد زيادة 20%',
        ])->assertStatus(200);

        $this->pushLlm(json_encode($this->sampleEvaluation(), JSON_UNESCAPED_UNICODE));

        $response = $this->authPost("/api/v1/user/ai-negotiator/sessions/{$sessionId}/end", []);

        $response->assertStatus(200)
            ->assertJsonPath('data.overall_score', 72);

        $this->assertCount(8, $response->json('data.scores'));
    }

    public function test_active_session_null_after_abandon(): void
    {
        $sessionId = $this->authPost('/api/v1/user/ai-negotiator/sessions', [])
            ->json('data.id');

        $this->authPost("/api/v1/user/ai-negotiator/sessions/{$sessionId}/abandon", [])
            ->assertStatus(200)
            ->assertJsonPath('data.session_state', 'abandoned');

        $this->authGet('/api/v1/user/ai-negotiator/sessions/active')
            ->assertStatus(200)
            ->assertJsonPath('data', null)
            ->assertJsonPath('key', 'messages.ai_negotiator.no_active_session');
    }

    public function test_credits_balance_shape(): void
    {
        $this->authGet('/api/v1/user/ai-negotiator/credits/balance')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'balance',
                    'allotment',
                    'consumed_this_cycle',
                    'cycle_ends_at',
                    'access_mode',
                ],
            ])
            ->assertJsonPath('data.balance', 3)
            ->assertJsonPath('data.access_mode', 'credits_based');
    }

    public function test_cross_user_session_access_forbidden(): void
    {
        $sessionId = $this->authPost('/api/v1/user/ai-negotiator/sessions', [])
            ->json('data.id');

        $other = $this->makeVerifiedUser('ain-other-' . uniqid() . '@example.com');
        $otherToken = $other->createToken('test')->plainTextToken;

        $this->resetAuthState();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $otherToken,
            'X-Context' => 'app',
            'Accept' => 'application/json',
        ])->getJson("/api/v1/user/ai-negotiator/sessions/{$sessionId}")
            ->assertStatus(403)
            ->assertJsonPath('key', 'messages.ai_negotiator.forbidden');
    }

    public function test_admin_index_returns_paginated_list(): void
    {
        $this->authPost('/api/v1/user/ai-negotiator/sessions', [])->assertStatus(200);

        $admin = $this->makeAdminWithPermissions([
            'admin.access',
            'ai_negotiator_session.view',
            'ai_negotiator_session.manage',
        ]);

        $this->adminGet($admin, '/api/v1/admin/ai-negotiator/sessions')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'session_state', 'session_type', 'difficulty', 'created_at'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_admin_without_session_view_gets_403(): void
    {
        $admin = $this->makeAdminWithPermissions(['admin.access']);

        $this->adminGet($admin, '/api/v1/admin/ai-negotiator/sessions')
            ->assertStatus(403)
            ->assertJsonPath('key', 'messages.permission.error');
    }

    private function reachSimulating(): int
    {
        $sessionId = $this->authPost('/api/v1/user/ai-negotiator/sessions', [])
            ->json('data.id');

        $this->pushLlm('سؤال؟');
        $this->authPost("/api/v1/user/ai-negotiator/sessions/{$sessionId}/messages", [
            'content' => 'جواب',
        ])->assertStatus(200);

        $this->pushLlm("<INTAKE_COMPLETE>");
        $this->pushLlm(json_encode($this->samplePersona(), JSON_UNESCAPED_UNICODE));
        $this->authPost("/api/v1/user/ai-negotiator/sessions/{$sessionId}/messages", [
            'content' => 'تفاصيل',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.session_state', 'simulating');

        return (int) $sessionId;
    }

    private function makeVerifiedUser(string $email, bool $verified = true): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'phone' => '05' . random_int(10000000, 99999999),
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'email_verified' => $verified,
            'is_guest' => false,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function makeAdminWithPermissions(array $permissionNames): User
    {
        $admin = $this->makeVerifiedUser('ain-admin-' . uniqid() . '@example.com');
        $role = Role::create([
            'name' => 'admin_' . uniqid(),
            'description' => 'test admin',
            'is_default' => false,
        ]);

        UserRole::create([
            'user_id' => $admin->id,
            'role_id' => $role->id,
        ]);

        foreach ($permissionNames as $name) {
            $permission = Permission::query()->where('name', $name)->firstOrFail();
            RolePermission::create([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ]);
        }

        return $admin;
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

    private function authHeaders(?string $token = null): array
    {
        return [
            'Authorization' => 'Bearer ' . ($token ?? $this->token),
            'X-Context' => 'app',
            'Accept' => 'application/json',
        ];
    }

    private function authGet(string $uri)
    {
        $this->resetAuthState();

        return $this->withHeaders($this->authHeaders())->getJson($uri);
    }

    private function authPost(string $uri, array $data = [])
    {
        $this->resetAuthState();

        return $this->withHeaders($this->authHeaders())->postJson($uri, $data);
    }

    private function adminGet(User $admin, string $uri)
    {
        $this->resetAuthState();
        $token = $admin->createToken('admin')->plainTextToken;

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Context' => 'dashboard',
            'Accept' => 'application/json',
        ])->getJson($uri);
    }

    private function resetAuthState(): void
    {
        Auth::forgetGuards();
        cache()->flush();
    }

    private function seedPermissions(): void
    {
        foreach ([
            'admin.access',
            'ai_negotiator_session.view',
            'ai_negotiator_session.manage',
            'ai_negotiator_knowledge.view',
            'ai_negotiator_knowledge.manage',
            'ai_negotiator_settings.manage',
        ] as $name) {
            Permission::create([
                'name' => $name,
                'description' => $name,
            ]);
        }
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
            $table->boolean('email_verified')->default(false);
            $table->boolean('is_guest')->default(false);
            $table->timestamp('guest_last_active_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_opened_app_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('inactive_since_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('device_token', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
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
