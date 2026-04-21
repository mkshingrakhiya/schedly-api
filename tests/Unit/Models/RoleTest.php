<?php

namespace Tests\Unit\Models;

use App\Enums\RoleSlug;
use App\Models\Role;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_find_by_slug_or_fail_returns_creator_role(): void
    {
        $role = Role::findBySlugOrFail(RoleSlug::Creator);

        $this->assertSame('creator', $role->slug);
    }

    public function test_find_by_slug_accepts_enum_or_string_and_matches_first(): void
    {
        $fromEnum = Role::findBySlug(RoleSlug::Creator);
        $fromString = Role::findBySlug('creator');

        $this->assertNotNull($fromEnum);
        $this->assertSame($fromEnum->id, $fromString?->id);
    }

    public function test_find_by_slug_returns_null_when_missing(): void
    {
        $this->assertNull(Role::findBySlug('role-that-does-not-exist'));
    }
}
