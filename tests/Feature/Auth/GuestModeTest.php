<?php

namespace Tests\Feature\Auth;

use App\Models\Users\Role;
use App\Models\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class GuestModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create([
            'name' => 'user',
            'description' => 'User role',
            'is_default' => true,
        ]);
    }

    public function test_guest_start_creates_guest_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/guest');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account_state', 'guest')
            ->assertJsonPath('data.is_guest', true)
            ->assertJsonStructure(['access_token']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'is_guest' => 1,
            'email' => null,
            'phone' => null,
        ]);
    }

    public function test_guest_is_blocked_from_verified_only_endpoints(): void
    {
        $guest = $this->postJson('/api/v1/auth/guest')->json();
        $token = $guest['access_token'];

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user/certificates');

        $response->assertStatus(403);
    }

    public function test_register_from_guest_updates_same_user_record(): void
    {
        $guest = $this->postJson('/api/v1/auth/guest')->json();
        $token = $guest['access_token'];
        $guestUserId = $guest['data']['id'];
        $otherUser = User::create([
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => 'other@example.com',
            'phone' => '01212',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'is_guest' => false,
            'email_verified' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/register-from-guest', [
                'user_id' => $otherUser->id,
                'first_name' => 'Ali',
                'last_name' => 'Masry',
                'email' => 'ali@example.com',
                'phone' => '09999999',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $guestUserId)
            ->assertJsonPath('data.account_state', 'registered_unverified');

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', [
            'id' => $guestUserId,
            'email' => 'ali@example.com',
            'is_guest' => 0,
            'email_verified' => 0,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'email' => 'other@example.com',
        ]);
    }

    public function test_register_from_guest_fails_for_non_guest_user(): void
    {
        $user = User::create([
            'first_name' => 'Normal',
            'last_name' => 'User',
            'email' => 'normal@example.com',
            'phone' => '01111',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'is_guest' => false,
            'email_verified' => true,
        ]);
        $user->userRoles()->create([
            'role_id' => Role::where('name', 'user')->first()->id,
            'created_at' => now(),
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/register-from-guest', [
                'first_name' => 'Ali',
                'last_name' => 'Masry',
                'email' => 'ali2@example.com',
                'phone' => '02222',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertStatus(400);
    }

    public function test_forgot_password_verify_otp_succeeds_while_guest_token_is_present(): void
    {
        config(['diplomasi.disable_app_registration' => false]);

        $guestUser = User::create([
            'first_name' => 'Learner',
            'last_name' => 'Guest',
            'email' => null,
            'phone' => null,
            'password' => null,
            'status' => 'active',
            'is_guest' => true,
            'email_verified' => false,
        ]);
        $guestToken = $guestUser->createToken('guest_auth_token')->plainTextToken;

        $target = User::create([
            'first_name' => 'Reset',
            'last_name' => 'Target',
            'email' => 'reset-target@example.com',
            'phone' => '04444',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
            'is_guest' => false,
            'email_verified' => true,
            'otp' => '12345',
            'otp_expire_at' => now()->addMinutes(5),
        ]);

        // App always attaches the guest Sanctum token; OTP must still resolve by email.
        $verify = $this->withHeader('Authorization', 'Bearer ' . $guestToken)
            ->postJson('/api/v1/auth/verify-otp', [
                'email' => 'reset-target@example.com',
                'otp' => '12345',
            ]);

        $verify->assertStatus(200)
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonStructure(['access_token']);

        $this->assertNull($target->fresh()->otp);
        $this->assertNotNull(PersonalAccessToken::findToken($guestToken));
    }

    public function test_verify_otp_rotates_all_tokens_after_guest_conversion(): void
    {
        $guest = $this->postJson('/api/v1/auth/guest')->json();
        $oldToken = $guest['access_token'];

        $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->postJson('/api/v1/auth/register-from-guest', [
                'first_name' => 'Ali',
                'last_name' => 'Masry',
                'email' => 'otp-user@example.com',
                'phone' => '03333',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertStatus(200);

        $user = User::where('email', 'otp-user@example.com')->firstOrFail();

        $verify = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->postJson('/api/v1/auth/verify-otp', [
                'email' => 'otp-user@example.com',
                'otp' => $user->otp,
            ]);

        $verify->assertStatus(200)
            ->assertJsonPath('data.account_state', 'registered_verified')
            ->assertJsonStructure(['access_token']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_guest' => 0,
            'email_verified' => 1,
        ]);

        $newToken = $verify->json('access_token');
        $this->assertNotEmpty($newToken);
        $this->assertNotSame($oldToken, $newToken);

        $this->assertNull(PersonalAccessToken::findToken($oldToken));
        $this->assertNotNull(PersonalAccessToken::findToken($newToken));
        $this->assertSame(1, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
    }
}
