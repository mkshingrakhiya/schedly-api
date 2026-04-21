<?php

namespace Tests\Feature\Content;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_access_posts(): void
    {
        $this->getJson('/api/v1/platforms')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_platforms(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this
            ->getJson('/api/v1/platforms')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'instagram'])
            ->assertJsonFragment(['slug' => 'facebook']);
    }
}
