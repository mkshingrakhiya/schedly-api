<?php

namespace Tests\Unit\Models;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class UserTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uuidv7_is_set_automatically_on_creation(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->uuid);
        $this->assertTrue(Str::isUuid($user->uuid));
        $this->assertSame(7, Uuid::fromString($user->uuid)->getFields()->getVersion());
    }

    public function test_role_id_is_not_mass_assignable(): void
    {
        $otherRole = Role::factory()->create();

        $user = new User([
            'name' => 'Test User',
            'email' => 'mass-assign-role@example.com',
            'password' => 'password1234',
            'role_id' => $otherRole->id,
        ]);

        $this->assertArrayNotHasKey('role_id', $user->getDirty());
    }
}
