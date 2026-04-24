<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceHeader;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\Http\Controllers\V1WorkspaceProbeController;
use Tests\TestCase;

class V1WorkspaceHeaderResolutionTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum'])
            ->get('api/v1/__workspace-probe', [V1WorkspaceProbeController::class, 'show']);
    }

    public function test_missing_workspace_header_returns_400(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this
            ->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/__workspace-probe')
            ->assertStatus(400)
            ->assertSeeText('The '.WorkspaceHeader::HEADER_NAME.' header is required.');
    }

    public function test_unknown_workspace_uuid_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this
            ->getJson('/api/v1/__workspace-probe', $this->workspaceHeader(Str::uuid()->toString()))
            ->assertNotFound();
    }

    public function test_valid_workspace_header_resolves_workspace(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::query()->findOrFail($workspace->owner_id);
        Sanctum::actingAs($owner);

        $this
            ->getJson('/api/v1/__workspace-probe', $this->workspaceHeader($workspace->uuid))
            ->assertOk()
            ->assertJsonPath('workspaceUuid', $workspace->uuid);
    }
}
