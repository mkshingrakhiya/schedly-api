<?php

namespace Tests\Unit\Models;

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
}
