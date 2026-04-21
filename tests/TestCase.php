<?php

namespace Tests;

use App\Models\Workspace;
use Database\Seeders\PlatformSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PlatformSeeder::class);
    }

    protected function workspaceHeader(Workspace $workspace): array
    {
        return ['X-Workspace-Uuid' => $workspace->uuid];
    }
}
