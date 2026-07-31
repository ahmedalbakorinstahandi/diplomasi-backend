<?php

namespace Tests\Feature\Negotiation;

use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationResponse;
use App\Models\Negotiation\NegotiationSituation;
use App\Models\Negotiation\UserNegotiationLevelProgress;
use App\Models\Negotiation\UserNegotiationSituationProgress;
use App\Models\Users\User;
use App\Services\NegotiationQuizService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NegotiationAssessmentApiTest extends TestCase
{
    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->app['db']->purge('sqlite');
        $this->app['db']->reconnect('sqlite');

        $this->createSchema();

        $this->user = User::create([
            'first_name' => 'App',
            'last_name' => 'User',
            'email' => 'nego-assess-' . uniqid() . '@example.com',
            'phone' => '01' . random_int(10000000, 99999999),
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_quick_test_start_payload_never_leaks_correct_answers(): void
    {
        $level = $this->makeLevel(1);
        $situation = $this->makeSituation($level, 1);

        $response = $this->authPost("/api/v1/user/negotiation/situations/{$situation->id}/quick-test/start");

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'attempt_id',
                    'quiz' => [
                        'seed',
                        'questions' => [
                            ['situation_id', 'asked_style', 'options' => [['id', 'response_text']]],
                        ],
                    ],
                ],
            ]);

        $json = $response->getContent();
        $this->assertStringNotContainsString('correct_response_id', $json);

        foreach ($response->json('data.quiz.questions') as $question) {
            $this->assertArrayNotHasKey('correct_response_id', $question);
            foreach ($question['options'] as $option) {
                $this->assertSame(['id', 'response_text'], array_keys($option));
            }
        }
    }

    public function test_quick_test_submit_grades_and_completes_situation_and_level(): void
    {
        $level = $this->makeLevel(1);
        $situation = $this->makeSituation($level, 1);

        $start = $this->authPost("/api/v1/user/negotiation/situations/{$situation->id}/quick-test/start")
            ->assertStatus(201)
            ->json('data');

        $attemptId = $start['attempt_id'];
        $byStyle = $situation->negotiationResponses->keyBy('style');

        $answers = [];
        foreach (NegotiationQuizService::STYLES as $style) {
            $answers[] = [
                'asked_style' => $style,
                'selected_response_id' => (int) $byStyle[$style]->id,
            ];
        }

        $submit = $this->authPost("/api/v1/user/negotiation/quick-test/{$attemptId}/submit", [
            'answers' => $answers,
        ]);

        $submit->assertStatus(200)
            ->assertJsonPath('data.status', 'finished')
            ->assertJsonPath('data.summary.correct_count', 3)
            ->assertJsonPath('data.summary.score', 100)
            ->assertJsonPath('data.results.0.is_correct', true);

        $this->assertNotNull($submit->json('data.results.0.correct_response_id'));
        $this->assertNotEmpty($submit->json('data.results.0.feedback'));

        $progress = UserNegotiationSituationProgress::where('user_id', $this->user->id)
            ->where('negotiation_situation_id', $situation->id)
            ->first();
        $this->assertTrue((bool) $progress->is_completed);

        $levelProgress = UserNegotiationLevelProgress::where('user_id', $this->user->id)
            ->where('negotiation_level_id', $level->id)
            ->first();
        $this->assertSame('completed', $levelProgress->status);
    }

    public function test_final_test_requires_completed_level_and_submit_is_non_gating(): void
    {
        $level = $this->makeLevel(1);
        $situations = [];
        for ($i = 1; $i <= 5; $i++) {
            $situations[] = $this->makeSituation($level, $i);
        }

        $this->authPost("/api/v1/user/negotiation/levels/{$level->id}/final-test/start")
            ->assertStatus(403);

        foreach ($situations as $situation) {
            $this->completeSituationViaApi($situation);
        }

        $levelProgress = UserNegotiationLevelProgress::where('user_id', $this->user->id)
            ->where('negotiation_level_id', $level->id)
            ->first();
        $completedAt = $levelProgress->completed_at;
        $score = $levelProgress->score;

        $start = $this->authPost("/api/v1/user/negotiation/levels/{$level->id}/final-test/start")
            ->assertStatus(201)
            ->json('data');

        $this->assertStringNotContainsString(
            'correct_response_id',
            json_encode($start['quiz'])
        );
        $this->assertCount(15, $start['quiz']['questions']);

        // Build correct answers using review/server seed via a second start's seed rebuild:
        // Use review isn't available until finished — grade via stored seed in DB + quiz service.
        $attemptId = $start['attempt_id'];
        $attempt = \App\Models\Negotiation\UserNegotiationFinalTestAttempt::find($attemptId);
        $quiz = app(NegotiationQuizService::class)->buildFinalTest($level, (int) $attempt->seed);

        $answers = [];
        foreach ($quiz['questions'] as $question) {
            $answers[] = [
                'situation_id' => $question['situation_id'],
                'asked_style' => $question['asked_style'],
                'selected_response_id' => $question['correct_response_id'],
            ];
        }

        $this->authPost("/api/v1/user/negotiation/final-test/{$attemptId}/submit", [
            'answers' => $answers,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'finished')
            ->assertJsonPath('data.summary.correct_count', 15);

        $levelProgress->refresh();
        $this->assertEquals(
            $completedAt?->toDateTimeString(),
            $levelProgress->completed_at?->toDateTimeString()
        );
        $this->assertEquals((string) $score, (string) $levelProgress->score);
    }

    public function test_replay_archive_and_review_reproduce_option_order(): void
    {
        $level = $this->makeLevel(1);
        $situation = $this->makeSituation($level, 1);

        $start1 = $this->authPost("/api/v1/user/negotiation/situations/{$situation->id}/quick-test/start")
            ->json('data');
        $attemptId = $start1['attempt_id'];
        $optionOrder = array_column($start1['quiz']['questions'][0]['options'], 'id');

        $byStyle = $situation->negotiationResponses->keyBy('style');
        $answers = array_map(fn ($style) => [
            'asked_style' => $style,
            'selected_response_id' => (int) $byStyle[$style]->id,
        ], NegotiationQuizService::STYLES);

        $this->authPost("/api/v1/user/negotiation/quick-test/{$attemptId}/submit", [
            'answers' => $answers,
        ])->assertStatus(200);

        // Replay after completion
        $replay = $this->authPost("/api/v1/user/negotiation/situations/{$situation->id}/quick-test/start")
            ->assertStatus(201)
            ->json('data');
        $this->assertNotEquals($attemptId, $replay['attempt_id']);

        $progress = UserNegotiationSituationProgress::where('user_id', $this->user->id)
            ->where('negotiation_situation_id', $situation->id)
            ->first();
        $this->assertSame('completed', $progress->status);

        $archive = $this->authGet("/api/v1/user/negotiation/situations/{$situation->id}/attempts")
            ->assertStatus(200)
            ->json('data');
        $this->assertCount(2, $archive);
        $this->assertSame($replay['attempt_id'], $archive[0]['id']);
        $this->assertSame($attemptId, $archive[1]['id']);

        $review = $this->authGet("/api/v1/user/negotiation/quick-test/{$attemptId}")
            ->assertStatus(200)
            ->assertJsonPath('data.mode', 'review')
            ->json('data');

        $reviewOrder = array_column($review['questions'][0]['options'], 'id');
        $this->assertSame($optionOrder, $reviewOrder);
        $this->assertNotNull($review['questions'][0]['selected_response_id']);
        $this->assertTrue($review['questions'][0]['is_correct']);
        $this->assertNotNull($review['questions'][0]['correct_response_id']);
        $this->assertNotEmpty($review['questions'][0]['feedback']);
    }

    public function test_partial_quick_test_submit_rejected_by_validation(): void
    {
        $level = $this->makeLevel(1);
        $situation = $this->makeSituation($level, 1);
        $attemptId = $this->authPost("/api/v1/user/negotiation/situations/{$situation->id}/quick-test/start")
            ->json('data.attempt_id');

        $this->authPost("/api/v1/user/negotiation/quick-test/{$attemptId}/submit", [
            'answers' => [
                ['asked_style' => 'gentle', 'selected_response_id' => 1],
            ],
        ])->assertStatus(422);
    }

    private function completeSituationViaApi(NegotiationSituation $situation): void
    {
        $start = $this->authPost("/api/v1/user/negotiation/situations/{$situation->id}/quick-test/start")
            ->json('data');
        $byStyle = $situation->fresh('negotiationResponses')->negotiationResponses->keyBy('style');
        $answers = array_map(fn ($style) => [
            'asked_style' => $style,
            'selected_response_id' => (int) $byStyle[$style]->id,
        ], NegotiationQuizService::STYLES);

        $this->authPost("/api/v1/user/negotiation/quick-test/{$start['attempt_id']}/submit", [
            'answers' => $answers,
        ])->assertStatus(200);
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Context' => 'app',
            'Accept' => 'application/json',
        ];
    }

    private function authGet(string $uri)
    {
        return $this->withHeaders($this->authHeaders())->getJson($uri);
    }

    private function authPost(string $uri, array $data = [])
    {
        return $this->withHeaders($this->authHeaders())->postJson($uri, $data);
    }

    private function makeLevel(int $order): NegotiationLevel
    {
        return NegotiationLevel::create([
            'title' => "Level {$order}",
            'order_index' => $order,
            'is_published' => true,
        ]);
    }

    private function makeSituation(NegotiationLevel $level, int $order): NegotiationSituation
    {
        $situation = NegotiationSituation::create([
            'negotiation_level_id' => $level->id,
            'prompt_text' => "Prompt {$order}",
            'prompt_type' => 'quote',
            'order_index' => $order,
            'is_published' => true,
            'is_free' => true,
        ]);

        foreach (NegotiationQuizService::STYLES as $style) {
            NegotiationResponse::create([
                'negotiation_situation_id' => $situation->id,
                'style' => $style,
                'response_text' => "Text {$style} {$order}",
                'explanation' => "Why {$style} {$order}",
            ]);
        }

        return $situation->fresh(['negotiationResponses']);
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
            $table->timestamps();
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

        Schema::create('negotiation_levels', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('subtitle', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('how_to_study')->nullable();
            $table->unsignedBigInteger('order_index')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('negotiation_situations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('negotiation_level_id');
            $table->text('prompt_text');
            $table->string('prompt_type')->default('quote');
            $table->unsignedBigInteger('order_index')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_free');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('negotiation_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('negotiation_situation_id');
            $table->string('style');
            $table->text('response_text');
            $table->text('explanation');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_negotiation_situation_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('negotiation_situation_id');
            $table->string('status')->default('not_started');
            $table->string('track_status')->default('locked');
            $table->boolean('is_completed')->default(false);
            $table->decimal('score', 6, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('user_negotiation_level_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('negotiation_level_id');
            $table->unsignedBigInteger('current_negotiation_situation_id')->nullable();
            $table->string('status')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('user_negotiation_situation_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('negotiation_situation_id');
            $table->string('status')->default('in_progress');
            $table->decimal('score', 6, 2)->nullable();
            $table->unsignedSmallInteger('total_questions')->default(3);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedBigInteger('seed')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('user_negotiation_situation_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_negotiation_situation_attempt_id');
            $table->unsignedBigInteger('negotiation_situation_id');
            $table->string('asked_style');
            $table->unsignedBigInteger('selected_negotiation_response_id')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('user_negotiation_final_test_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('negotiation_level_id');
            $table->string('status')->default('in_progress');
            $table->decimal('score', 6, 2)->nullable();
            $table->unsignedSmallInteger('total_questions')->default(15);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedBigInteger('seed')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('user_negotiation_final_test_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_negotiation_final_test_attempt_id');
            $table->unsignedBigInteger('negotiation_situation_id');
            $table->string('asked_style');
            $table->unsignedBigInteger('selected_negotiation_response_id')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->softDeletes();
        });
    }
}
