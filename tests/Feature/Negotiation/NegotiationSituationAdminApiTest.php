<?php

namespace Tests\Feature\Negotiation;

use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationResponse;
use App\Models\Negotiation\NegotiationSituation;
use App\Models\Negotiation\UserNegotiationSituationAttempt;
use App\Models\Negotiation\UserNegotiationSituationProgress;
use App\Models\Users\Permission;
use App\Models\Users\Role;
use App\Models\Users\RolePermission;
use App\Models\Users\User;
use App\Models\Users\UserRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NegotiationSituationAdminApiTest extends TestCase
{
    private User $admin;

    private string $token;

    private NegotiationLevel $level;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        $this->app['db']->purge('sqlite');
        $this->app['db']->reconnect('sqlite');

        $this->createSchema();
        $this->seedPermissions();

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'nego-sit-admin-' . uniqid() . '@example.com',
            'phone' => '01' . random_int(10000000, 99999999),
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $adminRole = Role::where('name', 'admin')->firstOrFail();
        UserRole::create([
            'user_id' => $this->admin->id,
            'role_id' => $adminRole->id,
        ]);

        $this->token = $this->admin->createToken('test')->plainTextToken;
        $this->level = NegotiationLevel::factory()->create([
            'title' => 'Level 1',
            'order_index' => 1,
            'is_published' => true,
        ]);
    }

    public function test_create_with_three_responses_succeeds_and_is_unpublished(): void
    {
        $response = $this->adminPost('/api/v1/admin/negotiation-situations', [
            'negotiation_level_id' => $this->level->id,
            'prompt_text' => 'This is above my budget.',
            'prompt_context' => 'Lead-in',
            'prompt_type' => 'quote',
            'insight' => 'Teaching note',
            'is_free' => true,
            'is_published' => true,
            'responses' => $this->threeResponses(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.order_index', 1)
            ->assertJsonPath('data.prompt_text', 'This is above my budget.')
            ->assertJsonCount(3, 'data.responses')
            ->assertJsonMissingPath('data.access_status');

        $styles = collect($response->json('data.responses'))->pluck('style')->all();
        $this->assertSame(['gentle', 'diplomatic', 'firm'], $styles);

        $this->assertDatabaseCount('negotiation_responses', 3);
    }

    public function test_create_rejects_when_responses_count_or_styles_invalid(): void
    {
        $this->adminPost('/api/v1/admin/negotiation-situations', [
            'negotiation_level_id' => $this->level->id,
            'prompt_text' => 'Prompt',
            'prompt_type' => 'quote',
            'is_free' => true,
            'responses' => array_slice($this->threeResponses(), 0, 2),
        ])->assertStatus(422);

        $this->adminPost('/api/v1/admin/negotiation-situations', [
            'negotiation_level_id' => $this->level->id,
            'prompt_text' => 'Prompt',
            'prompt_type' => 'quote',
            'is_free' => true,
            'responses' => [
                ['style' => 'gentle', 'response_text' => 'A', 'explanation' => 'A'],
                ['style' => 'gentle', 'response_text' => 'B', 'explanation' => 'B'],
                ['style' => 'firm', 'response_text' => 'C', 'explanation' => 'C'],
            ],
        ])->assertStatus(422);
    }

    public function test_publish_rejected_when_incomplete_and_accepted_when_complete(): void
    {
        $created = $this->adminPost('/api/v1/admin/negotiation-situations', [
            'negotiation_level_id' => $this->level->id,
            'prompt_text' => 'Prompt',
            'prompt_type' => 'quote',
            'is_free' => true,
            'responses' => $this->threeResponses(),
        ])->assertStatus(201);

        $situationId = $created->json('data.id');

        NegotiationResponse::where('negotiation_situation_id', $situationId)
            ->where('style', 'gentle')
            ->update(['explanation' => '']);

        $this->adminPut("/api/v1/admin/negotiation-situations/{$situationId}", [
            'is_published' => true,
        ])->assertStatus(422);

        $this->assertDatabaseHas('negotiation_situations', [
            'id' => $situationId,
            'is_published' => 0,
        ]);

        $this->adminPut("/api/v1/admin/negotiation-situations/{$situationId}", [
            'is_published' => true,
            'responses' => $this->threeResponses([
                'gentle' => ['response_text' => 'Fixed gentle', 'explanation' => 'Fixed why'],
            ]),
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('negotiation_situations', [
            'id' => $situationId,
            'is_published' => 1,
        ]);
    }

    public function test_update_rejects_invalid_responses_shape(): void
    {
        $situation = $this->createSituationWithResponses($this->level, 1);

        $this->adminPut("/api/v1/admin/negotiation-situations/{$situation->id}", [
            'responses' => [
                ['style' => 'gentle', 'response_text' => 'A', 'explanation' => 'A'],
                ['style' => 'diplomatic', 'response_text' => 'B', 'explanation' => 'B'],
            ],
        ])->assertStatus(422);
    }

    public function test_per_level_reorder_does_not_renumber_other_levels(): void
    {
        $levelA = $this->level;
        $levelB = NegotiationLevel::factory()->create([
            'title' => 'Level 2',
            'order_index' => 2,
        ]);

        $a1 = $this->createSituationWithResponses($levelA, 1, 'A1');
        $a2 = $this->createSituationWithResponses($levelA, 2, 'A2');
        $a3 = $this->createSituationWithResponses($levelA, 3, 'A3');

        $b1 = $this->createSituationWithResponses($levelB, 1, 'B1');
        $b2 = $this->createSituationWithResponses($levelB, 2, 'B2');

        $this->adminPut("/api/v1/admin/negotiation-situations/{$a3->id}/reorder", [
            'new_order_index' => 1,
        ])->assertStatus(200);

        $this->assertSame(1, $a3->fresh()->order_index);
        $this->assertSame(2, $a1->fresh()->order_index);
        $this->assertSame(3, $a2->fresh()->order_index);

        $this->assertSame(1, $b1->fresh()->order_index);
        $this->assertSame(2, $b2->fresh()->order_index);
    }

    public function test_create_assigns_order_index_per_level(): void
    {
        $levelB = NegotiationLevel::factory()->create(['title' => 'L2', 'order_index' => 2]);

        $this->createSituationWithResponses($this->level, 1);
        $this->createSituationWithResponses($this->level, 2);

        $response = $this->adminPost('/api/v1/admin/negotiation-situations', [
            'negotiation_level_id' => $levelB->id,
            'prompt_text' => 'First on B',
            'prompt_type' => 'quote',
            'is_free' => true,
            'responses' => $this->threeResponses(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.order_index', 1);
    }

    public function test_delete_soft_deletes_situation_and_responses_preserving_progress_and_attempts(): void
    {
        $situation = $this->createSituationWithResponses($this->level, 1);

        $learner = User::create([
            'first_name' => 'Learner',
            'last_name' => 'User',
            'email' => 'learner-sit-' . uniqid() . '@example.com',
            'phone' => '01' . random_int(10000000, 99999999),
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $progress = UserNegotiationSituationProgress::create([
            'user_id' => $learner->id,
            'negotiation_situation_id' => $situation->id,
            'status' => 'in_progress',
            'track_status' => 'open',
            'is_completed' => false,
            'score' => 50,
            'started_at' => now(),
        ]);

        $attempt = UserNegotiationSituationAttempt::create([
            'user_id' => $learner->id,
            'negotiation_situation_id' => $situation->id,
            'status' => 'finished',
            'score' => 100,
            'total_questions' => 3,
            'correct_count' => 3,
            'seed' => 1,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $responseIds = $situation->negotiationResponses()->pluck('id')->all();

        $this->adminDelete("/api/v1/admin/negotiation-situations/{$situation->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('negotiation_situations', ['id' => $situation->id]);
        foreach ($responseIds as $responseId) {
            $this->assertSoftDeleted('negotiation_responses', ['id' => $responseId]);
        }

        $this->assertDatabaseHas('user_negotiation_situation_progress', [
            'id' => $progress->id,
            'negotiation_situation_id' => $situation->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('user_negotiation_situation_attempts', [
            'id' => $attempt->id,
            'negotiation_situation_id' => $situation->id,
            'deleted_at' => null,
        ]);

        $this->assertNotNull(UserNegotiationSituationProgress::find($progress->id));
        $this->assertNotNull(UserNegotiationSituationAttempt::find($attempt->id));
    }

    public function test_index_filters_by_level_published_and_free(): void
    {
        $otherLevel = NegotiationLevel::factory()->create(['title' => 'Other', 'order_index' => 2]);

        $this->createSituationWithResponses($this->level, 1, 'Free published', true, true);
        $this->createSituationWithResponses($this->level, 2, 'Paid draft', false, false);
        $this->createSituationWithResponses($otherLevel, 1, 'Other level', true, true);

        $this->adminGet('/api/v1/admin/negotiation-situations?negotiation_level_id=' . $this->level->id)
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        $this->adminGet('/api/v1/admin/negotiation-situations?negotiation_level_id=' . $this->level->id . '&is_published=1')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.is_published', true);

        $this->adminGet('/api/v1/admin/negotiation-situations?negotiation_level_id=' . $this->level->id . '&is_free=0')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.is_free', false);

        $this->adminGet('/api/v1/admin/negotiation-situations?search=Free')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_app_context_is_forbidden(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Context' => 'app',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/negotiation-situations')
            ->assertStatus(403);
    }

    public function test_missing_permission_is_forbidden(): void
    {
        $limited = User::create([
            'first_name' => 'Limited',
            'last_name' => 'Admin',
            'email' => 'limited-sit-' . uniqid() . '@example.com',
            'phone' => '01' . random_int(10000000, 99999999),
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $role = Role::create([
            'name' => 'limited_admin_sit',
            'description' => 'Access only',
            'is_default' => false,
        ]);

        RolePermission::create([
            'role_id' => $role->id,
            'permission_id' => Permission::where('name', 'admin.access')->firstOrFail()->id,
        ]);

        UserRole::create([
            'user_id' => $limited->id,
            'role_id' => $role->id,
        ]);

        $token = $limited->createToken('test')->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'X-Context' => 'dashboard',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/negotiation-situations')
            ->assertStatus(403);
    }

    public function test_show_returns_nested_responses(): void
    {
        $situation = $this->createSituationWithResponses($this->level, 1);

        $this->adminGet("/api/v1/admin/negotiation-situations/{$situation->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $situation->id)
            ->assertJsonCount(3, 'data.responses')
            ->assertJsonPath('data.responses.0.style', 'gentle');
    }

    /**
     * @param  array<string, array{response_text?: string, explanation?: string}>  $overrides
     * @return list<array{style: string, response_text: string, explanation: string}>
     */
    private function threeResponses(array $overrides = []): array
    {
        $rows = [
            'gentle' => ['style' => 'gentle', 'response_text' => 'Gentle text', 'explanation' => 'Gentle why'],
            'diplomatic' => ['style' => 'diplomatic', 'response_text' => 'Diplomatic text', 'explanation' => 'Diplomatic why'],
            'firm' => ['style' => 'firm', 'response_text' => 'Firm text', 'explanation' => 'Firm why'],
        ];

        foreach ($overrides as $style => $patch) {
            $rows[$style] = array_merge($rows[$style], $patch, ['style' => $style]);
        }

        return array_values($rows);
    }

    private function createSituationWithResponses(
        NegotiationLevel $level,
        int $order,
        string $prompt = 'Prompt',
        bool $isFree = true,
        bool $isPublished = false,
    ): NegotiationSituation {
        $situation = NegotiationSituation::factory()->create([
            'negotiation_level_id' => $level->id,
            'prompt_text' => $prompt,
            'order_index' => $order,
            'is_free' => $isFree,
            'is_published' => $isPublished,
            'prompt_type' => 'quote',
        ]);

        foreach (['gentle', 'diplomatic', 'firm'] as $style) {
            NegotiationResponse::factory()->style($style)->create([
                'negotiation_situation_id' => $situation->id,
                'response_text' => "{$style} text",
                'explanation' => "{$style} why",
            ]);
        }

        return $situation->fresh(['negotiationResponses']);
    }

    private function adminHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Context' => 'dashboard',
            'Accept' => 'application/json',
        ];
    }

    private function adminGet(string $uri)
    {
        return $this->withHeaders($this->adminHeaders())->getJson($uri);
    }

    private function adminPost(string $uri, array $data = [])
    {
        return $this->withHeaders($this->adminHeaders())->postJson($uri, $data);
    }

    private function adminPut(string $uri, array $data = [])
    {
        return $this->withHeaders($this->adminHeaders())->putJson($uri, $data);
    }

    private function adminDelete(string $uri)
    {
        return $this->withHeaders($this->adminHeaders())->deleteJson($uri);
    }

    private function seedPermissions(): void
    {
        $role = Role::create([
            'name' => 'admin',
            'description' => 'Dashboard admin role',
            'is_default' => false,
        ]);

        $names = [
            'admin.access',
            'negotiation_situation.view',
            'negotiation_situation.create',
            'negotiation_situation.update',
            'negotiation_situation.delete',
            'negotiation_situation.reorder',
        ];

        foreach ($names as $name) {
            $permission = Permission::create([
                'name' => $name,
                'description' => $name,
            ]);

            RolePermission::create([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
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
            $table->softDeletes();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
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
            $table->text('prompt_context')->nullable();
            $table->text('insight')->nullable();
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
            $table->unique(['negotiation_situation_id', 'style', 'deleted_at'], 'neg_responses_unique');
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
    }
}
