<?php

namespace Tests\Feature\Negotiation;

use App\Models\Billing\Subscription;
use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationResponse;
use App\Models\Negotiation\NegotiationSituation;
use App\Models\Negotiation\UserNegotiationSituationAttempt;
use App\Models\Users\User;
use App\Services\NegotiationQuizService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NegotiationLibraryApiTest extends TestCase
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
            'email' => 'nego-api-' . uniqid() . '@example.com',
            'phone' => '01' . random_int(10000000, 99999999),
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_levels_list_shows_access_status_and_counts(): void
    {
        $l1 = $this->makeLevel(1, 'Direct');
        $l2 = $this->makeLevel(2, 'Layered');
        $this->makeSituation($l1, 1, true);
        $this->makeSituation($l1, 2, true);
        $this->makeSituation($l2, 1, true);

        // Complete level 1
        foreach (NegotiationSituation::where('negotiation_level_id', $l1->id)->get() as $s) {
            $this->completeSituation($s);
        }

        $response = $this->authGet('/api/v1/user/negotiation/levels');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame('completed', $data[0]['access_status']);
        $this->assertSame(2, $data[0]['situations_count']);
        $this->assertSame(2, $data[0]['progress']['completed_situations']);
        $this->assertSame('open', $data[1]['access_status']);
        $this->assertSame(1, $data[1]['situations_count']);
        $this->assertArrayNotHasKey('description', $data[0]);
    }

    public function test_locked_level_situations_endpoint_is_blocked(): void
    {
        $l1 = $this->makeLevel(1, 'L1');
        $l2 = $this->makeLevel(2, 'L2');
        $this->makeSituation($l1, 1, true);
        $this->makeSituation($l2, 1, true);

        $this->authGet("/api/v1/user/negotiation/levels/{$l2->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.access_status', 'locked')
            ->assertJsonPath('data.description', null)
            ->assertJsonStructure(['data' => ['how_to_study', 'description']]);

        $this->authGet("/api/v1/user/negotiation/levels/{$l2->id}/situations")
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('details.access_status', 'locked');
    }

    public function test_paid_situation_subscription_guard(): void
    {
        $level = $this->makeLevel(1, 'L1');
        $free = $this->makeSituation($level, 1, true);
        $paid = $this->makeSituation($level, 2, false);
        $this->completeSituation($free);

        $guestResponse = $this->authGet("/api/v1/user/negotiation/situations/{$paid->id}");
        $guestResponse->assertStatus(403)
            ->assertJsonPath('details.access_status', 'locked_by_subscription')
            ->assertJsonPath('details.access_reason', 'subscription');

        Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => 1,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'auto_renew' => true,
            'price' => 10,
            'currency' => 'SAR',
        ]);

        // Clear User::auth request cache
        cache()->flush();

        $this->authGet("/api/v1/user/negotiation/situations/{$paid->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $paid->id)
            ->assertJsonPath('data.access_status', 'open')
            ->assertJsonCount(3, 'data.responses');
    }

    public function test_situation_detail_returns_three_responses_with_explanations(): void
    {
        $level = $this->makeLevel(1, 'L1');
        $situation = $this->makeSituation($level, 1, true);

        $response = $this->authGet("/api/v1/user/negotiation/situations/{$situation->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.prompt_text', $situation->prompt_text)
            ->assertJsonCount(3, 'data.responses');

        $styles = collect($response->json('data.responses'))->pluck('style')->all();
        $this->assertSame(['gentle', 'diplomatic', 'firm'], $styles);
        $this->assertNotEmpty($response->json('data.responses.0.explanation'));
        $this->assertArrayNotHasKey('correct_response_id', $response->json('data'));
    }

    public function test_note_upsert_and_fetch_round_trip(): void
    {
        $level = $this->makeLevel(1, 'L1');
        $situation = $this->makeSituation($level, 1, true);

        $this->authGet("/api/v1/user/negotiation/situations/{$situation->id}/note")
            ->assertStatus(200)
            ->assertJsonPath('data.note_text', null);

        $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/user/negotiation/situations/{$situation->id}/note", [
                'note_text' => 'My summary in my words',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.note_text', 'My summary in my words');

        $this->authGet("/api/v1/user/negotiation/situations/{$situation->id}/note")
            ->assertStatus(200)
            ->assertJsonPath('data.note_text', 'My summary in my words');

        $this->authGet("/api/v1/user/negotiation/levels/{$level->id}/situations")
            ->assertStatus(200)
            ->assertJsonPath('data.0.has_note', true);
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

    private function makeLevel(int $order, string $title): NegotiationLevel
    {
        return NegotiationLevel::create([
            'title' => $title,
            'subtitle' => $title . ' sub',
            'description' => null,
            'how_to_study' => null,
            'order_index' => $order,
            'is_published' => true,
        ]);
    }

    private function makeSituation(NegotiationLevel $level, int $order, bool $isFree): NegotiationSituation
    {
        $situation = NegotiationSituation::create([
            'negotiation_level_id' => $level->id,
            'prompt_text' => "Prompt {$order}",
            'prompt_type' => 'quote',
            'order_index' => $order,
            'is_published' => true,
            'is_free' => $isFree,
        ]);

        foreach (NegotiationQuizService::STYLES as $style) {
            NegotiationResponse::create([
                'negotiation_situation_id' => $situation->id,
                'style' => $style,
                'response_text' => "Text {$style}",
                'explanation' => "Why {$style}",
            ]);
        }

        return $situation->fresh(['negotiationResponses']);
    }

    private function completeSituation(NegotiationSituation $situation): void
    {
        UserNegotiationSituationAttempt::create([
            'user_id' => $this->user->id,
            'negotiation_situation_id' => $situation->id,
            'status' => 'finished',
            'score' => 100,
            'total_questions' => 3,
            'correct_count' => 3,
            'seed' => 1,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        app(\App\Services\NegotiationProgressService::class)
            ->updateSituationProgress($situation->id, $this->user->id);
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
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 10)->nullable();
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

        Schema::create('user_negotiation_situation_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('negotiation_situation_id');
            $table->text('note_text')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
