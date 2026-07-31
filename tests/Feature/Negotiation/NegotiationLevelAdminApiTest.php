<?php

namespace Tests\Feature\Negotiation;

use App\Models\Negotiation\NegotiationLevel;
use App\Models\Negotiation\NegotiationResponse;
use App\Models\Negotiation\NegotiationSituation;
use App\Models\Negotiation\UserNegotiationLevelProgress;
use App\Models\Users\Permission;
use App\Models\Users\Role;
use App\Models\Users\RolePermission;
use App\Models\Users\User;
use App\Models\Users\UserRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NegotiationLevelAdminApiTest extends TestCase
{
    private User $admin;

    private string $token;

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
            'email' => 'nego-level-admin-' . uniqid() . '@example.com',
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
    }

    public function test_index_lists_levels_with_meta(): void
    {
        NegotiationLevel::factory()->create(['title' => 'Level A', 'order_index' => 1]);
        NegotiationLevel::factory()->create(['title' => 'Level B', 'order_index' => 2]);

        $response = $this->adminGet('/api/v1/admin/negotiation-levels');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.title', 'Level A')
            ->assertJsonPath('data.0.situations_count', 0)
            ->assertJsonMissingPath('data.0.access_status')
            ->assertJsonMissingPath('data.0.progress');
    }

    public function test_create_forces_is_published_false(): void
    {
        $response = $this->adminPost('/api/v1/admin/negotiation-levels', [
            'title' => 'New Level',
            'subtitle' => 'Sub',
            'description' => 'Desc',
            'how_to_study' => 'Study tip',
            'is_published' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'New Level')
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.order_index', 1);

        $this->assertDatabaseHas('negotiation_levels', [
            'title' => 'New Level',
            'is_published' => 0,
            'order_index' => 1,
        ]);
    }

    public function test_show_update_and_publish_via_update(): void
    {
        $level = NegotiationLevel::factory()->create([
            'title' => 'Draft',
            'order_index' => 1,
            'is_published' => false,
        ]);

        $this->adminGet("/api/v1/admin/negotiation-levels/{$level->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $level->id)
            ->assertJsonPath('data.is_published', false);

        $this->adminPut("/api/v1/admin/negotiation-levels/{$level->id}", [
            'title' => 'Published Level',
            'is_published' => true,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'Published Level')
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('negotiation_levels', [
            'id' => $level->id,
            'title' => 'Published Level',
            'is_published' => 1,
        ]);
    }

    public function test_reorder_changes_order_index(): void
    {
        $first = NegotiationLevel::factory()->create(['title' => 'First', 'order_index' => 1]);
        $second = NegotiationLevel::factory()->create(['title' => 'Second', 'order_index' => 2]);
        $third = NegotiationLevel::factory()->create(['title' => 'Third', 'order_index' => 3]);

        $this->adminPut("/api/v1/admin/negotiation-levels/{$third->id}/reorder", [
            'new_order_index' => 1,
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame(1, $third->fresh()->order_index);
        $this->assertSame(2, $first->fresh()->order_index);
        $this->assertSame(3, $second->fresh()->order_index);
    }

    public function test_delete_soft_deletes_level_and_preserves_user_progress(): void
    {
        $level = NegotiationLevel::factory()->create(['title' => 'To Delete', 'order_index' => 1]);

        $situation = NegotiationSituation::create([
            'negotiation_level_id' => $level->id,
            'prompt_text' => 'Prompt',
            'prompt_type' => 'quote',
            'order_index' => 1,
            'is_published' => false,
            'is_free' => true,
        ]);

        NegotiationResponse::create([
            'negotiation_situation_id' => $situation->id,
            'style' => 'gentle',
            'response_text' => 'Gentle',
            'explanation' => 'Why gentle',
        ]);

        $learner = User::create([
            'first_name' => 'Learner',
            'last_name' => 'User',
            'email' => 'learner-' . uniqid() . '@example.com',
            'phone' => '01' . random_int(10000000, 99999999),
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $progress = UserNegotiationLevelProgress::create([
            'user_id' => $learner->id,
            'negotiation_level_id' => $level->id,
            'current_negotiation_situation_id' => $situation->id,
            'status' => 'in_progress',
            'score' => 10,
            'started_at' => now(),
            'completed_at' => null,
        ]);

        $this->adminDelete("/api/v1/admin/negotiation-levels/{$level->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('negotiation_levels', ['id' => $level->id]);
        $this->assertSoftDeleted('negotiation_situations', ['id' => $situation->id]);
        $this->assertSoftDeleted('negotiation_responses', ['id' => NegotiationResponse::withTrashed()->first()->id]);

        $this->assertDatabaseHas('user_negotiation_level_progress', [
            'id' => $progress->id,
            'user_id' => $learner->id,
            'negotiation_level_id' => $level->id,
            'status' => 'in_progress',
            'deleted_at' => null,
        ]);

        $this->assertNotNull(UserNegotiationLevelProgress::find($progress->id));
    }

    public function test_app_context_is_forbidden(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'X-Context' => 'app',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/negotiation-levels')
            ->assertStatus(403);
    }

    public function test_missing_permission_is_forbidden(): void
    {
        $limited = User::create([
            'first_name' => 'Limited',
            'last_name' => 'Admin',
            'email' => 'limited-' . uniqid() . '@example.com',
            'phone' => '01' . random_int(10000000, 99999999),
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $role = Role::create([
            'name' => 'limited_admin',
            'description' => 'Access only',
            'is_default' => false,
        ]);

        $access = Permission::where('name', 'admin.access')->firstOrFail();
        RolePermission::create([
            'role_id' => $role->id,
            'permission_id' => $access->id,
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
        ])->getJson('/api/v1/admin/negotiation-levels')
            ->assertStatus(403);
    }

    public function test_index_filters_by_search_and_is_published(): void
    {
        NegotiationLevel::factory()->create([
            'title' => 'Direct situations',
            'order_index' => 1,
            'is_published' => true,
        ]);
        NegotiationLevel::factory()->create([
            'title' => 'Critical moments',
            'order_index' => 2,
            'is_published' => false,
        ]);

        $this->adminGet('/api/v1/admin/negotiation-levels?search=Direct')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Direct situations');

        $this->adminGet('/api/v1/admin/negotiation-levels?is_published=1')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.is_published', true);
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
            'negotiation_level.view',
            'negotiation_level.create',
            'negotiation_level.update',
            'negotiation_level.delete',
            'negotiation_level.reorder',
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
    }
}
