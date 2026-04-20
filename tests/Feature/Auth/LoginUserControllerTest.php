<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LoginUserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_login_and_receives_token(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'createdAt', 'updatedAt'],
                'token',
            ]);

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => $user::class,
            'tokenable_id' => $user->id,
            'name' => 'api',
        ]);
    }

    public function test_login_normalizes_email(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => '  JANE@EXAMPLE.COM  ',
                'password' => 'password1234',
            ])
            ->assertOk()
            ->assertJsonPath('user.email', 'jane@example.com');
    }

    public function test_login_rejects_wrong_password_with_generic_message(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'jane@example.com',
                'password' => 'wrong-password',
            ])
            ->assertUnprocessable()
            ->assertJson(['message' => 'The provided credentials are incorrect.']);
    }

    public function test_login_rejects_unknown_email_with_same_message(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'nobody@example.com',
                'password' => 'password1234',
            ])
            ->assertUnprocessable()
            ->assertJson(['message' => 'The provided credentials are incorrect.']);
    }

    public function test_login_requires_valid_email(): void
    {
        $this
            ->postJson('/api/v1/auth/login', [
                'email' => 'not-an-email',
                'password' => 'password1234',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this
            ->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
