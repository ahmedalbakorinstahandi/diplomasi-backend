<?php

namespace Tests\Unit\Negotiation;

use App\Events\UserNegotiationLevelCompleted;
use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationSituation;
use App\Models\Negotiation\UserNegotiationLevelProgress;
use App\Models\Negotiation\UserNegotiationSituationAttempt;
use App\Models\Users\User;
use App\Services\NegotiationProgressService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Self-contained schema (avoids MySQL-only migrations under RefreshDatabase).
 */
class NegotiationProgressServiceTest extends TestCase
{
    private NegotiationProgressService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['database.connections.sqlite.foreign_key_constraints' => true]);

        $this->app['db']->purge('sqlite');
        $this->app['db']->reconnect('sqlite');

        $this->createSchema();
        $this->service = app(NegotiationProgressService::class);
    }

    public function test_situation_two_is_locked_until_one_is_completed(): void
    {
        $user = $this->makeUser();
        $level = $this->makeLevel(1);
        $s1 = $this->makeSituation($level, 1, isFree: true);
        $s2 = $this->makeSituation($level, 2, isFree: true);

        $this->assertSame('open', $this->service->getSituationAccessStatus($s1, $user->id));
        $this->assertTrue($this->service->canAccessSituation($s1, $user->id));

        $this->assertSame('locked', $this->service->getSituationAccessStatus($s2, $user->id));
        $this->assertFalse($this->service->canAccessSituation($s2, $user->id));
        $this->assertSame(
            NegotiationProgressService::ACCESS_REASON_PROGRESS,
            $this->service->getSituationBlockingReason($s2, $user->id)
        );

        $this->finishQuickTest($user->id, $s1->id, score: 100);
        $this->service->updateSituationProgress($s1->id, $user->id);

        $this->assertSame('completed', $this->service->getSituationAccessStatus($s1, $user->id));
        $this->assertSame('open', $this->service->getSituationAccessStatus($s2, $user->id));
        $this->assertTrue($this->service->canAccessSituation($s2, $user->id));
    }

    public function test_paid_situation_is_skipped_in_the_chain(): void
    {
        $user = $this->makeUser();
        $level = $this->makeLevel(1);
        $s1 = $this->makeSituation($level, 1, isFree: true);
        $s2Paid = $this->makeSituation($level, 2, isFree: false);
        $s3 = $this->makeSituation($level, 3, isFree: true);

        $this->assertSame(
            NegotiationProgressService::ACCESS_REASON_SUBSCRIPTION,
            $this->service->getSituationBlockingReason($s2Paid, $user->id)
        );
        $this->assertSame('locked_by_subscription', $this->service->getSituationAccessStatus($s2Paid, $user->id));

        $this->assertSame(
            NegotiationProgressService::ACCESS_REASON_PROGRESS,
            $this->service->getSituationBlockingReason($s3, $user->id)
        );

        $this->finishQuickTest($user->id, $s1->id);
        $this->service->updateSituationProgress($s1->id, $user->id);

        $this->assertNull($this->service->getSituationBlockingReason($s3, $user->id));
        $this->assertSame('open', $this->service->getSituationAccessStatus($s3, $user->id));
        $this->assertTrue($this->service->canAccessSituation($s3, $user->id));
        $this->assertSame('locked_by_subscription', $this->service->getSituationAccessStatus($s2Paid, $user->id));
    }

    public function test_level_completes_only_when_all_published_situations_are_done(): void
    {
        Event::fake([UserNegotiationLevelCompleted::class]);

        $user = $this->makeUser();
        $level = $this->makeLevel(1);
        $s1 = $this->makeSituation($level, 1, isFree: true);
        $s2 = $this->makeSituation($level, 2, isFree: true);
        $unpublished = $this->makeSituation($level, 3, isFree: true, published: false);

        $this->finishQuickTest($user->id, $s1->id, score: 80);
        $this->service->updateSituationProgress($s1->id, $user->id);

        $this->assertFalse($this->service->isNegotiationLevelCompleted($level, $user->id));
        Event::assertNotDispatched(UserNegotiationLevelCompleted::class);

        $this->finishQuickTest($user->id, $s2->id, score: 100);
        $this->service->updateSituationProgress($s2->id, $user->id);

        $this->assertTrue($this->service->isNegotiationLevelCompleted($level, $user->id));
        $this->assertSame('completed', $this->service->getNegotiationLevelAccessStatus($level, $user->id));
        Event::assertDispatched(UserNegotiationLevelCompleted::class);

        $this->assertDatabaseHas('user_negotiation_level_progress', [
            'user_id' => $user->id,
            'negotiation_level_id' => $level->id,
            'status' => 'completed',
            'score' => 90,
        ]);

        $this->assertFalse($this->service->isSituationCompleted($unpublished, $user->id));
    }

    public function test_level_completion_is_permanent_when_new_situation_is_added(): void
    {
        Event::fake([UserNegotiationLevelCompleted::class]);

        $user = $this->makeUser();
        $level = $this->makeLevel(1);
        $s1 = $this->makeSituation($level, 1, isFree: true);
        $s2 = $this->makeSituation($level, 2, isFree: true);

        $this->finishQuickTest($user->id, $s1->id);
        $this->service->updateSituationProgress($s1->id, $user->id);
        $this->finishQuickTest($user->id, $s2->id);
        $this->service->updateSituationProgress($s2->id, $user->id);

        $this->assertTrue($this->service->isNegotiationLevelCompleted($level, $user->id));
        Event::assertDispatchedTimes(UserNegotiationLevelCompleted::class, 1);

        $progress = UserNegotiationLevelProgress::where('user_id', $user->id)
            ->where('negotiation_level_id', $level->id)
            ->first();
        $completedAt = $progress->completed_at;
        $score = $progress->score;

        $this->makeSituation($level, 0, isFree: true);

        $this->assertTrue($this->service->isNegotiationLevelCompleted($level, $user->id));
        $this->assertSame('completed', $this->service->getNegotiationLevelAccessStatus($level, $user->id));

        $this->service->checkAndUpdateNegotiationLevelCompletion($level->id, $user->id);

        $progress->refresh();
        $this->assertSame('completed', $progress->status);
        $this->assertEquals($completedAt?->toDateTimeString(), $progress->completed_at?->toDateTimeString());
        $this->assertEquals((string) $score, (string) $progress->score);
        Event::assertDispatchedTimes(UserNegotiationLevelCompleted::class, 1);

        $this->assertSame('completed', $this->service->getSituationAccessStatus($s1, $user->id));
        $this->assertTrue($this->service->canAccessSituation($s1, $user->id));
        $this->assertSame('completed', $this->service->getSituationAccessStatus($s2, $user->id));
    }

    public function test_in_progress_situation_stays_accessible_if_prerequisite_is_inserted(): void
    {
        $user = $this->makeUser();
        $level = $this->makeLevel(1);
        $s1 = $this->makeSituation($level, 1, isFree: true);
        $s2 = $this->makeSituation($level, 2, isFree: true);

        $this->finishQuickTest($user->id, $s1->id);
        $this->service->updateSituationProgress($s1->id, $user->id);

        UserNegotiationSituationAttempt::create([
            'user_id' => $user->id,
            'negotiation_situation_id' => $s2->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
        $this->service->updateSituationProgress($s2->id, $user->id);

        $this->assertSame('in_progress', $this->service->getSituationAccessStatus($s2, $user->id));

        $this->makeSituation($level, 0, isFree: true);

        $this->assertSame('in_progress', $this->service->getSituationAccessStatus($s2, $user->id));
        $this->assertTrue($this->service->canAccessSituation($s2, $user->id));
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
            $table->string('status');
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
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->softDeletes();
        });
    }

    private function makeUser(): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'nego-' . uniqid() . '@example.com',
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

    private function makeSituation(
        NegotiationLevel $level,
        int $orderIndex,
        bool $isFree,
        bool $published = true
    ): NegotiationSituation {
        return NegotiationSituation::create([
            'negotiation_level_id' => $level->id,
            'prompt_text' => 'Prompt ' . $orderIndex,
            'prompt_type' => 'quote',
            'order_index' => $orderIndex,
            'is_published' => $published,
            'is_free' => $isFree,
        ]);
    }

    private function finishQuickTest(int $userId, int $situationId, float $score = 100): void
    {
        UserNegotiationSituationAttempt::create([
            'user_id' => $userId,
            'negotiation_situation_id' => $situationId,
            'status' => 'finished',
            'score' => $score,
            'total_questions' => 3,
            'correct_count' => 3,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
