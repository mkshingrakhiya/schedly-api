<?php

namespace Tests\Unit\Models;

use App\Domain\Content\Models\Channel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChannelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_access_token_and_refresh_token_are_readable_from_attributes(): void
    {
        $access = 'access-secret-value';
        $refresh = 'refresh-secret-value';

        $channel = Channel::factory()->create([
            'access_token' => $access,
            'refresh_token' => $refresh,
        ]);

        $channel->refresh();

        $this->assertSame($access, $channel->access_token);
        $this->assertSame($refresh, $channel->refresh_token);
    }

    public function test_access_token_and_refresh_token_are_hidden_from_array_and_json_serialization(): void
    {
        $channel = Channel::factory()->create([
            'access_token' => 'must-not-leak-access',
            'refresh_token' => 'must-not-leak-refresh',
        ]);

        $array = $channel->toArray();

        $this->assertArrayNotHasKey('access_token', $array);
        $this->assertArrayNotHasKey('refresh_token', $array);

        $decoded = json_decode($channel->toJson(), true);
        $this->assertIsArray($decoded);
        $this->assertArrayNotHasKey('access_token', $decoded);
        $this->assertArrayNotHasKey('refresh_token', $decoded);
    }

    public function test_access_token_and_refresh_token_can_be_revealed_with_make_visible(): void
    {
        $channel = Channel::factory()->create([
            'access_token' => 'visible-access',
            'refresh_token' => 'visible-refresh',
        ]);

        $array = $channel->makeVisible(['access_token', 'refresh_token'])->toArray();

        $this->assertSame('visible-access', $array['access_token']);
        $this->assertSame('visible-refresh', $array['refresh_token']);
    }
}
