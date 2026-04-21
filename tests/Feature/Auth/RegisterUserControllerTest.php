<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegisterUserControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_register_and_receives_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password1234',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'user' => [
                    'uuid',
                    'name',
                    'email',
                    'role' => ['uuid', 'slug', 'name', 'description'],
                    'createdAt',
                    'updatedAt',
                ],
                'token',
            ]);

        $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

        $this->assertTrue(Str::isUuid($response->json('user.uuid')));
        $this->assertSame($user->uuid, $response->json('user.uuid'));
        $this->assertSame('customer', $response->json('user.role.slug'));
        $creatorRoleId = Role::findBySlugOrFail(RoleSlug::CUSTOMER)->id;
        $this->assertSame($creatorRoleId, $user->role_id);
        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'uuid' => $user->uuid,
            'role_id' => $creatorRoleId,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => $user::class,
            'tokenable_id' => $user->id,
            'name' => 'api',
        ]);
    }

    public function test_registration_normalizes_name_and_email(): void
    {
        $this
            ->postJson('/api/v1/auth/register', [
                'name' => '   Jane    Doe   ',
                'email' => '  JANE@EXAMPLE.COM  ',
                'password' => 'password1234',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }

    public function test_registration_requires_unique_email(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $this
            ->postJson('/api/v1/auth/register', [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'password1234',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
