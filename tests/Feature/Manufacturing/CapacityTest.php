<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Covers capacity load and bottleneck reporting.
 *
 * Work center CRUD is covered by WorkCenterTest.
 */
class CapacityTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.capacity.view',
            'manufacturing.capacity.create',
            'manufacturing.capacity.edit',
            'manufacturing.capacity.delete',
        ]);
    }

    public function test_capacity_load_returns_data(): void
    {
        $from = now()->subMonth()->format('Y-m-d');
        $to   = now()->format('Y-m-d');

        $this->getJson(
            "/api/v1/manufacturing/capacity/load?from={$from}&to={$to}",
            $this->authHeaders()
        )->assertOk()->assertJsonPath('success', true);
    }

    public function test_bottlenecks_returns_data(): void
    {
        $from = now()->subMonth()->format('Y-m-d');
        $to   = now()->format('Y-m-d');

        $this->getJson(
            "/api/v1/manufacturing/capacity/bottlenecks?from={$from}&to={$to}",
            $this->authHeaders()
        )->assertOk()->assertJsonPath('success', true);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/manufacturing/capacity/load')->assertUnauthorized();
    }
}
