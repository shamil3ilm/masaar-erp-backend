<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\WorkCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Covers work center CRUD and calendar exceptions.
 *
 * Capacity planning endpoints are covered by CapacityTest.
 */
class WorkCenterTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const BASE_URL = '/api/v1/manufacturing/work-centers';

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

    public function test_index_returns_paginated(): void
    {
        WorkCenter::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->getJson(self::BASE_URL, $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_store_creates_work_center(): void
    {
        $this->postJson(
            self::BASE_URL,
            [
                'code'             => 'WC-TEST-01',
                'name'             => 'Assembly Line 1',
                'work_center_type' => 'assembly',
            ],
            $this->authHeaders()
        )->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('work_centers', [
            'code'            => 'WC-TEST-01',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_store_requires_code(): void
    {
        $this->postJson(self::BASE_URL, ['name' => 'Assembly Line'], $this->authHeaders())
            ->assertUnprocessable();
    }

    public function test_store_requires_name(): void
    {
        $this->postJson(self::BASE_URL, ['code' => 'WC-002'], $this->authHeaders())
            ->assertUnprocessable();
    }

    public function test_store_rejects_duplicate_code_in_same_organization(): void
    {
        WorkCenter::factory()->create([
            'organization_id' => $this->organization->id,
            'code'            => 'WC-DUP',
        ]);

        $this->postJson(
            self::BASE_URL,
            ['code' => 'WC-DUP', 'name' => 'Another Centre'],
            $this->authHeaders()
        )->assertUnprocessable();
    }

    public function test_show_returns_work_center(): void
    {
        $workCenter = WorkCenter::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->getJson(self::BASE_URL . "/{$workCenter->id}", $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_update_changes_name(): void
    {
        $workCenter = WorkCenter::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->putJson(
            self::BASE_URL . "/{$workCenter->id}",
            ['name' => 'Updated Center Name'],
            $this->authHeaders()
        )->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('work_centers', [
            'id'   => $workCenter->id,
            'name' => 'Updated Center Name',
        ]);
    }

    public function test_update_keeps_code_when_omitted(): void
    {
        $workCenter = WorkCenter::factory()->create([
            'organization_id' => $this->organization->id,
            'code'            => 'WC-KEEP',
        ]);

        $this->putJson(
            self::BASE_URL . "/{$workCenter->id}",
            ['name' => 'Renamed Only'],
            $this->authHeaders()
        )->assertOk();

        $this->assertDatabaseHas('work_centers', [
            'id'   => $workCenter->id,
            'code' => 'WC-KEEP',
        ]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $workCenter = WorkCenter::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->deleteJson(self::BASE_URL . "/{$workCenter->id}", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('work_centers', ['id' => $workCenter->id]);
    }

    public function test_store_exception_records_calendar_override(): void
    {
        $workCenter = WorkCenter::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->postJson(
            self::BASE_URL . "/{$workCenter->id}/exceptions",
            [
                'exception_date'  => now()->addDay()->toDateString(),
                'available_hours' => 4,
                'reason'          => 'Planned maintenance',
            ],
            $this->authHeaders()
        )->assertCreated()->assertJsonPath('success', true);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson(self::BASE_URL)->assertUnauthorized();
    }
}
