<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\DistributionCycle;
use App\Models\Core\Organization;
use App\Services\Accounting\DistributionCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class DistributionCycleTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.controlling.cycle.view',
            'accounting.controlling.cycle.create',
            'accounting.controlling.cycle.update',
            'accounting.controlling.cycle.delete',
            'accounting.controlling.cycle.execute',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCycle(array $overrides = []): DistributionCycle
    {
        return DistributionCycle::create(array_merge([
            'organization_id' => $this->organization->id,
            'name'            => 'Distribution Cycle ' . fake()->unique()->numerify('###'),
            'fiscal_year'     => 2025,
            'period_from'     => 1,
            'period_to'       => 12,
            'status'          => DistributionCycle::STATUS_OPEN,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeCycle();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/distribution-cycles');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/distribution-cycles', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_cycle(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/distribution-cycles', [
                'name'        => 'Overhead Distribution',
                'fiscal_year' => 2025,
                'period_from' => 1,
                'period_to'   => 3,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $cycle = $this->makeCycle();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/distribution-cycles/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_open_cycle(): void
    {
        $cycle = $this->makeCycle(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $cycle->fresh()->name);
    }

    public function test_update_rejects_non_open_cycle(): void
    {
        $cycle = $this->makeCycle(['status' => DistributionCycle::STATUS_EXECUTED]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_open_cycle(): void
    {
        $cycle = $this->makeCycle();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid);

        $response->assertStatus(200);
        $this->assertNull(DistributionCycle::find($cycle->id));
    }

    public function test_destroy_rejects_non_open_cycle(): void
    {
        $cycle = $this->makeCycle(['status' => DistributionCycle::STATUS_EXECUTED]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Execute
    // -------------------------------------------------------------------------

    public function test_execute_validates_period_required(): void
    {
        $cycle = $this->makeCycle();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid . '/execute', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Postings
    // -------------------------------------------------------------------------

    public function test_postings_returns_list(): void
    {
        $cycle = $this->makeCycle();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/distribution-cycles/' . $cycle->uuid . '/postings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/controlling/distribution-cycles')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Filters, tenant isolation and concurrent transitions
    // -------------------------------------------------------------------------

    public function test_index_applies_filters_and_excludes_other_organizations(): void
    {
        $this->makeCycle(['name' => 'Open 2025']);
        $this->makeCycle([
            'name'        => 'Executed 2026',
            'fiscal_year' => 2026,
            'status'      => DistributionCycle::STATUS_EXECUTED,
        ]);
        $this->makeCycle(['name' => 'Foreign', 'organization_id' => Organization::factory()->create()->id]);

        $all = $this->withToken($this->token)->getJson('/api/v1/controlling/distribution-cycles?status=');
        $all->assertStatus(200)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonStructure(['data' => [['executed_by']]]);
        $this->assertSame(['Executed 2026', 'Open 2025'], array_column($all->json('data'), 'name'));

        $byYear = $this->withToken($this->token)->getJson('/api/v1/controlling/distribution-cycles?fiscal_year=2025');
        $this->assertSame(['Open 2025'], array_column($byYear->json('data'), 'name'));

        $byStatus = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/distribution-cycles?status=executed&per_page=3');
        $byStatus->assertJsonPath('meta.per_page', 3);
        $this->assertSame(['Executed 2026'], array_column($byStatus->json('data'), 'name'));
    }

    public function test_store_sets_the_organization_and_opens_the_cycle(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/controlling/distribution-cycles', [
                'name'        => 'Stored Distribution',
                'fiscal_year' => 2025,
                'period_from' => 1,
                'period_to'   => 2,
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Distribution cycle created.')
            ->assertJsonPath('data.status', DistributionCycle::STATUS_OPEN)
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_execute_rechecks_the_status_on_the_current_row(): void
    {
        $stale = $this->makeCycle();
        DistributionCycle::whereKey($stale->id)->update(['status' => DistributionCycle::STATUS_EXECUTED]);

        $this->expectException(InvalidArgumentException::class);

        app(DistributionCycleService::class)->execute($stale, 1);
    }

    public function test_reverse_rechecks_the_status_on_the_current_row(): void
    {
        $stale = $this->makeCycle(['status' => DistributionCycle::STATUS_EXECUTED]);
        DistributionCycle::whereKey($stale->id)->update(['status' => DistributionCycle::STATUS_REVERSED]);

        $this->expectException(InvalidArgumentException::class);

        app(DistributionCycleService::class)->reverse($stale, 1);
    }
}
