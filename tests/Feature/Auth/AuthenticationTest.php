<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\InteractsWithTenants;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use InteractsWithTenants;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@demo.test',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@demo.test',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'token_type', 'access_token', 'user'])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'owner@demo.test',
            'password' => Hash::make('secret-password'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@demo.test',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@demo.test',
            'password' => 'secret-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    /**
     * A wrong password and an unknown account must be indistinguishable, so the
     * endpoint cannot be used to discover which emails are registered.
     */
    public function test_login_error_is_identical_for_unknown_email_and_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'owner@demo.test',
            'password' => Hash::make('secret-password'),
        ]);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@demo.test',
            'password' => 'wrong-password',
        ]);

        $unknownEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@demo.test',
            'password' => 'secret-password',
        ]);

        $this->assertSame(
            $wrongPassword->json('errors.email.0'),
            $unknownEmail->json('errors.email.0')
        );
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_a_deactivated_account(): void
    {
        User::factory()->create([
            'email' => 'disabled@demo.test',
            'password' => Hash::make('secret-password'),
            'status' => 'inactive',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'disabled@demo.test',
            'password' => 'secret-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_authenticated_user_can_call_me(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'status', 'is_platform_user', 'permissions', 'companies']]);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'unauthenticated');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // The token row itself must be gone from the database.
        $this->assertDatabaseCount('personal_access_tokens', 0);

        /*
         * The revoked token must no longer authenticate.
         *
         * forgetGuards() is required because Laravel caches the resolved user
         * inside the auth guard for the lifetime of the test process. Without
         * it the assertion would pass/fail on stale state rather than on the
         * revocation actually happening.
         */
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_me_never_exposes_the_password_hash(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/auth/me');

        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('remember_token', $response->getContent());
    }

    public function test_login_is_rate_limited_against_brute_force(): void
    {
        User::factory()->create([
            'email' => 'owner@demo.test',
            'password' => Hash::make('secret-password'),
        ]);

        // The auth limiter allows 5 attempts per minute per IP.
        $lastStatus = null;

        for ($i = 0; $i < 8; $i++) {
            $lastStatus = $this->postJson('/api/v1/auth/login', [
                'email' => 'owner@demo.test',
                'password' => 'wrong-password',
            ])->getStatusCode();
        }

        $this->assertSame(429, $lastStatus, 'Brute-force attempts must eventually be throttled.');
    }
}
