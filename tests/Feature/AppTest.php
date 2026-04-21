<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_returns_a_successful_response(): void
    {
        $response = $this->get('/api/ping');

        $response->assertStatus(200);
    }
}
