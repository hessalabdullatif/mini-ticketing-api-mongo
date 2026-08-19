<?php

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    #[Test]
    public function a_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name'                  => 'Hessa',
            'email'                 => 'hessa@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email', 'hessa@test.com')
            ->assertJsonStructure(['user', 'access_token', 'token_type']);
    }

    #[Test]
    public function registration_assigns_the_user_role_regardless_of_what_is_sent(): void
    {
        // someone trying to make themselves an admin at signup
        $this->postJson('/api/register', [
            'name'                  => 'Sneaky',
            'email'                 => 'sneaky@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'admin',
        ]);

        $this->assertSame('user', User::where('email', 'sneaky@test.com')->first()->role);
    }

    #[Test]
    public function a_duplicate_email_is_rejected(): void
    {
        // Mongo has no unique constraint — this rule is the only thing
        // preventing two accounts on the same address
        User::create([
            'name'     => 'First',
            'email'    => 'taken@test.com',
            'password' => 'password123',
            'role'     => 'user',
        ]);

        $this->postJson('/api/register', [
            'name'                  => 'Second',
            'email'                 => 'taken@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    #[Test]
    public function a_mismatched_password_confirmation_is_rejected(): void
    {
        $this->postJson('/api/register', [
            'name'                  => 'Hessa',
            'email'                 => 'hessa@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'different',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    #[Test]
    public function the_password_is_stored_hashed(): void
    {
        $this->postJson('/api/register', [
            'name'                  => 'Hessa',
            'email'                 => 'hessa@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $stored = User::where('email', 'hessa@test.com')->first()->password;

        $this->assertNotSame('password123', $stored);
        $this->assertStringStartsWith('$2y$', $stored);
    }

    #[Test]
    public function a_user_can_log_in(): void
    {
        User::create([
            'name'     => 'Hessa',
            'email'    => 'hessa@test.com',
            'password' => 'password123',
            'role'     => 'user',
        ]);

        $this->postJson('/api/login', [
            'email'    => 'hessa@test.com',
            'password' => 'password123',
        ])->assertStatus(200)->assertJsonStructure(['access_token']);
    }

    #[Test]
    public function a_wrong_password_gives_a_deliberately_vague_message(): void
    {
        User::create([
            'name'     => 'Hessa',
            'email'    => 'hessa@test.com',
            'password' => 'password123',
            'role'     => 'user',
        ]);

        // the same message as an unknown email, so nobody can enumerate accounts
        $this->postJson('/api/login', [
            'email'    => 'hessa@test.com',
            'password' => 'wrongpassword',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
    }

    #[Test]
    public function an_unknown_email_gives_the_identical_message(): void
    {
        $this->postJson('/api/login', [
            'email'    => 'nobody@test.com',
            'password' => 'password123',
        ])->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
    }

    #[Test]
    public function a_user_token_carries_only_order_scopes(): void
    {
        User::create([
            'name'     => 'Regular',
            'email'    => 'user@test.com',
            'password' => 'password123',
            'role'     => 'user',
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'user@test.com',
            'password' => 'password123',
        ]);

        $scopes = $this->scopesFromToken($response->json('access_token'));

        $this->assertContains('orders:create', $scopes);
        $this->assertNotContains('events:create', $scopes);
    }

    #[Test]
    public function an_admin_token_carries_event_scopes_too(): void
    {
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.com',
            'password' => 'password123',
            'role'     => 'admin',
        ]);

        $response = $this->postJson('/api/login', [
            'email'    => 'admin@test.com',
            'password' => 'password123',
        ]);

        $scopes = $this->scopesFromToken($response->json('access_token'));

        $this->assertContains('events:create', $scopes);
        $this->assertContains('events:manage', $scopes);
    }

    // decodes the JWT payload without verifying the signature —
    // we only want to read the claims, not authenticate here
    private function scopesFromToken(string $token): array
    {
        $payload = explode('.', $token)[1];
        $decoded = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);

        return $decoded['scopes'] ?? [];
    }
}
