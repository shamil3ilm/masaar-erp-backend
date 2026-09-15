<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\ModuleReadinessResult;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the readiness result history: a module's results for the caller's
 * organization, latest run first, paginated.
 */
class ModuleReadinessEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.settings.view']);
    }

    public function test_results_are_listed_latest_first_for_the_organization_and_module(): void
    {
        $older = $this->readinessResult($this->organization->id, 'sales', now()->subDay());
        $newer = $this->readinessResult($this->organization->id, 'sales', now());
        $this->readinessResult($this->organization->id, 'hr', now());
        $this->readinessResult(Organization::factory()->create()->id, 'sales', now());

        $this->apiGet('/module-readiness/modules/sales/readiness-results?per_page=1')
            ->assertOk()
            ->assertJsonPath('message', 'Readiness results retrieved successfully.')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.id', $newer->id);

        $this->assertNotSame($older->id, $newer->id);
    }

    private function readinessResult(int $organizationId, string $module, \DateTimeInterface $runAt): ModuleReadinessResult
    {
        return ModuleReadinessResult::create([
            'organization_id' => $organizationId,
            'module' => $module,
            'run_at' => $runAt,
            'overall_status' => 'pass',
            'results' => [],
        ]);
    }
}
