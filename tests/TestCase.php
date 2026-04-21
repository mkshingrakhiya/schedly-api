<?php

namespace Tests;

use App\Support\WorkspaceHeader;
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

    /**
     * @return array<string, string>
     */
    protected function workspaceHeader(string $workspaceUuid): array
    {
        return [WorkspaceHeader::HEADER_NAME => $workspaceUuid];
    }
}
