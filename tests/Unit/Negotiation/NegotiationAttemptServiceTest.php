<?php

namespace Tests\Unit\Negotiation;

use App\Events\UserNegotiationLevelCompleted;
use App\Http\Services\Negotiation\NegotiationAttemptService;
use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationResponse;
use App\Models\Negotiation\NegotiationSituation;
use App\Models\Negotiation\UserNegotiationFinalTestAttempt;
use App\Models\Negotiation\UserNegotiationFinalTestAttemptAnswer;
use App\Models\Negotiation\UserNegotiationLevelProgress;
use App\Models\Negotiation\UserNegotiationSituationAttempt;
use App\Models\Negotiation\UserNegotiationSituationAttemptAnswer;
use App\Models\Negotiation\UserNegotiationSituationProgress;
use App\Models\Users\User;
use App\Services\NegotiationProgressService;
use App\Services\NegotiationQuizService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NegotiationAttemptServiceTest extends TestCase
{
    private NegotiationAttemptService $service;

    private NegotiationProgressService $progressService;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->app['db']->purge('sqlite');
        $this->app['db']->reconnect('sqlite');

        $this->createSchema();

        $this->progressService = app(NegotiationProgressService::class);
        $this->service = app(NegotiationAttemptService::class);
    }

    public function test_start_quick_test_creates_in_progress_attempt_with_seed(): void
    {
        $user = $this->makeUser();
        $level = $this->makeLevel(1);
        $situation = $this->makeSituationWithResponses($level, 1);

        $result = $this->service->startQuickTest($situation->id, $user->id);

        $attempt = $result['attempt'];
        $this->assertSame('in_progress', $attempt->status);
        $this->assertNotNull($attempt->seed);
        $this->assertSame(3, (int) $attempt->total_questions);
        $this->assertCount(3, $result['client']['questions']);
        $this->assertArrayNotHasKey('correct_response_id', $result['client']['questions'][0]);
        $this->assertArrayHasKey('correct_response_id', $result['server']['questions'][0]);

        $progress = UserNegotiationSituationProgress::where('user_id', $user->id)
            ->where('negotiation_situation_id', $situation->id)
            ->first();
        $this->assertNotNull($progress);
        $this->assertSame('in_progress', $progress->status);
        $this->assertFalse((bool) $progress->is_completed);
    }

    public function test_submit_quick_test_grades_stores_answers_and_completes_situation(): void
    {
        Event::fake([UserNegotiationLevelCompleted::class]);

        $user = $this->makeUser();
        $level = $this->makeLevel(1);
        $situation = $this->makeSituationWithResponses($level, 1);

        $started = $this->service->startQuickTest($situation->id, $user->id);
        $attempt = $started['attempt'];
        $byStyle = $situation->negotiationResponses->keyBy('style');

        $answers = [];
        foreach (NegotiationQuizService::STYLES as $style) {
            $answers[] = [
                'asked_style' => $style,
                'selected_response_id' => (int) $byStyle[$style]->id,
            ];
        }

        $submitted = $this->service->submitQuickTest($attempt->id, $user->id, $answers);

        $this->assertSame('finished', $submitted['attempt']->status);
        $this->assertSame(3, (int) $submitted['attempt']->correct_count);
        $this->assertSame(100.0, (float) $submitted['attempt']->score);
        $this->assertCount(3, $submitted['results']);
        $this->assertSame(3, UserNegotiationSituationAttemptAnswer::where(
            'user_negotiation_situation_attempt_id',
            $attempt->id
        )->count());

        $progress = UserNegotiationSituationProgress::where('user_id', $user->id)
            ->where('negotiation_situation_id', $situation->id)
            ->first();
        $this->assertTrue((bool) $progress->is_completed);
        $this->assertSame('completed', $progress->status);

        // Single situation level → level completes
        $this->assertTrue($this->progressService->isNegotiationLevelCompleted($level, $user->id));
        Event::assertDispatched(UserNegotiationLevelCompleted::class);
    }

    public function test_partial_quick_test_submit_is_rejected(): void
    {
        $user = $this->makeUser();
        $level = $this->makeLevel(1);
        $situation = $this->makeSituationWithResponses($level, 1);
        $started = $this->service->startQuickTest($situation->id, $user->id);

        try {
            $this->service->submitQuickTest($started['attempt']->id, $user->id, [
                ['asked_style' => 'gentle', 'selected_response_id' => 1],
            ]);
            $this->fail('Expected HttpResponseException for incomplete answers');
        } catch (HttpResponseException $e) {
            $this->assertSame(400, $e->getResponse()->getStatusCode());
        }

        $started['attempt']->refresh();
        $this->assertSame('in_progress', $started['attempt']->status);
    }

    public function test_replay_after_completion_does_not_downgrade_completed_status(): void
    {
        $user = $this->makeUser();
        $level = $this->makeLevel(1);
        $situation = $this->makeSituationWithResponses($level, 1);

        $first = $this->service->startQuickTest($situation->id, $user->id);
        $byStyle = $situation->negotiationResponses->keyBy('style');
        $answers = array_map(fn ($style) => [
            'asked_style' => $style,
            'selected_response_id' => (int) $byStyle[$style]->id,
        ], NegotiationQuizService::STYLES);
        $this->service->submitQuickTest($first['attempt']->id, $user->id, $answers);

        $progress = UserNegotiationSituationProgress::where('user_id', $user->id)
            ->where('negotiation_situation_id', $situation->id)
            ->first();
        $this->assertSame('completed', $progress->status);
        $completedAt = $progress->completed_at;

        $replay = $this->service->startQuickTest($situation->id, $user->id);
        $this->assertSame('in_progress', $replay['attempt']->status);
        $this->assertNotEquals($first['attempt']->id, $replay['attempt']->id);

        $progress->refresh();
        $this->assertSame('completed', $progress->status);
        $this->assertTrue((bool) $progress->is_completed);
        $this->assertEquals(
            $completedAt?->toDateTimeString(),
            $progress->completed_at?->toDateTimeString()
        );
    }

    public function test_final_test_requires_completed_level_and_never_changes_progress(): void
    {
        $user = $this->makeUser();
        $level = $this->makeLevel(1);

        // Bank needs >= 15 questions → at least 5 published situations with full responses.
        $situations = [];
        for ($i = 1; $i <= 5; $i++) {
            $situations[] = $this->makeSituationWithResponses($level, $i);
        }

        try {
            $this->service->startFinalTest($level->id, $user->id);
            $this->fail('Expected final test to require level completion');
        } catch (HttpResponseException $e) {
            $this->assertSame(403, $e->getResponse()->getStatusCode());
        }

        foreach ($situations as $situation) {
            $started = $this->service->startQuickTest($situation->id, $user->id);
            $byStyle = $situation->fresh('negotiationResponses')->negotiationResponses->keyBy('style');
            $answers = array_map(fn ($style) => [
                'asked_style' => $style,
                'selected_response_id' => (int) $byStyle[$style]->id,
            ], NegotiationQuizService::STYLES);
            $this->service->submitQuickTest($started['attempt']->id, $user->id, $answers);
        }

        $this->assertTrue($this->progressService->isNegotiationLevelCompleted($level, $user->id));
        $levelProgress = UserNegotiationLevelProgress::where('user_id', $user->id)
            ->where('negotiation_level_id', $level->id)
            ->first();
        $levelCompletedAt = $levelProgress->completed_at;
        $levelScore = $levelProgress->score;

        $final = $this->service->startFinalTest($level->id, $user->id);
        $this->assertSame('in_progress', $final['attempt']->status);
        $this->assertCount(15, $final['client']['questions']);
        $this->assertArrayNotHasKey('correct_response_id', $final['client']['questions'][0]);

        $answers = [];
        foreach ($final['server']['questions'] as $question) {
            $answers[] = [
                'situation_id' => $question['situation_id'],
                'asked_style' => $question['asked_style'],
                'selected_response_id' => $question['correct_response_id'],
            ];
        }

        $submitted = $this->service->submitFinalTest($final['attempt']->id, $user->id, $answers);
        $this->assertSame('finished', $submitted['attempt']->status);
        $this->assertSame(15, (int) $submitted['attempt']->correct_count);
        $this->assertSame(
            15,
            UserNegotiationFinalTestAttemptAnswer::where(
                'user_negotiation_final_test_attempt_id',
                $final['attempt']->id
            )->count()
        );

        $levelProgress->refresh();
        $this->assertSame('completed', $levelProgress->status);
        $this->assertEquals(
            $levelCompletedAt?->toDateTimeString(),
            $levelProgress->completed_at?->toDateTimeString()
        );
        $this->assertEquals((string) $levelScore, (string) $levelProgress->score);
    }

    public function test_archive_lists_return_newest_first(): void
    {
        $user = $this->makeUser();
        $level = $this->makeLevel(1);
        $situation = $this->makeSituationWithResponses($level, 1);

        $a1 = $this->service->startQuickTest($situation->id, $user->id)['attempt'];
        // Force older started_at
        $a1->started_at = now()->subMinutes(10);
        $a1->save();

        $a2 = $this->service->startQuickTest($situation->id, $user->id)['attempt'];
        $a2->started_at = now()->subMinutes(1);
        $a2->save();

        $list = $this->service->listSituationAttempts($situation->id, $user->id);
        $this->assertCount(2, $list);
        $this->assertSame($a2->id, $list[0]->id);
        $this->assertSame($a1->id, $list[1]->id);

        // Complete level so final tests can start (single situation)
        $byStyle = $situation->negotiationResponses->keyBy('style');
        $this->service->submitQuickTest($a2->id, $user->id, array_map(fn ($style) => [
            'asked_style' => $style,
            'selected_response_id' => (int) $byStyle[$style]->id,
        ], NegotiationQuizService::STYLES));

        // Need bank >= 15 for final test — add 4 more situations and complete them
        for ($i = 2; $i <= 5; $i++) {
            $s = $this->makeSituationWithResponses($level, $i);
            // Level already completed permanently; still can take quick tests for bank content
            // but canAccess may lock later situations. Complete via finished attempts + progress update.
            UserNegotiationSituationAttempt::create([
                'user_id' => $user->id,
                'negotiation_situation_id' => $s->id,
                'status' => 'finished',
                'score' => 100,
                'total_questions' => 3,
                'correct_count' => 3,
                'seed' => 1,
                'started_at' => now(),
                'finished_at' => now(),
            ]);
            $this->progressService->updateSituationProgress($s->id, $user->id);
        }

        $f1 = $this->service->startFinalTest($level->id, $user->id)['attempt'];
        $f1->started_at = now()->subMinutes(5);
        $f1->save();
        $f2 = $this->service->startFinalTest($level->id, $user->id)['attempt'];

        $finalList = $this->service->listFinalTestAttempts($level->id, $user->id);
        $this->assertCount(2, $finalList);
        $this->assertSame($f2->id, $finalList[0]->id);
        $this->assertSame($f1->id, $finalList[1]->id);
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
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status');
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

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'attempt-' . uniqid() . '@example.com',
            'phone' => '01' . random_int(10000000, 99999999),
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
    }

    private function makeLevel(int $orderIndex): NegotiationLevel
    {
        return NegotiationLevel::create([
            'title' => 'Level ' . $orderIndex,
            'order_index' => $orderIndex,
            'is_published' => true,
        ]);
    }

    private function makeSituationWithResponses(NegotiationLevel $level, int $orderIndex): NegotiationSituation
    {
        $situation = NegotiationSituation::create([
            'negotiation_level_id' => $level->id,
            'prompt_text' => 'Prompt ' . $orderIndex,
            'prompt_type' => 'quote',
            'order_index' => $orderIndex,
            'is_published' => true,
            'is_free' => true,
        ]);

        foreach (NegotiationQuizService::STYLES as $style) {
            NegotiationResponse::create([
                'negotiation_situation_id' => $situation->id,
                'style' => $style,
                'response_text' => "Response {$style} {$orderIndex}",
                'explanation' => "Why {$style} {$orderIndex}",
            ]);
        }

        return $situation->fresh(['negotiationResponses']);
    }
}
