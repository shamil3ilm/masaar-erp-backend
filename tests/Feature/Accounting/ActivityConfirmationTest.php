<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\ActivityConfirmation;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\ActivityType;
use App\Models\Core\Organization;
use App\Services\Accounting\ActivityConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ActivityConfirmationTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.controlling.activity-confirmation.view',
            'accounting.controlling.activity-confirmation.create',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCostCenter(): CostCenter
    {
        return CostCenter::create([
            'organization_id' => $this->organization->id,
            'code'            => 'CC-' . fake()->unique()->numerify('###'),
            'name'            => 'Test Cost Center',
            'status'          => 'active',
        ]);
    }

    private function makeActivityType(): ActivityType
    {
        return ActivityType::create([
            'organization_id' => $this->organization->id,
            'code'            => 'AT-' . fake()->unique()->numerify('###'),
            'name'            => 'Test Activity',
            'unit_of_measure' => 'hours',
        ]);
    }

    private function makeConfirmation(CostCenter $cc, ActivityType $at, array $overrides = []): ActivityConfirmation
    {
        return ActivityConfirmation::create(array_merge([
            'organization_id'     => $this->organization->id,
            'confirmation_number' => 'CONF-' . fake()->unique()->numerify('########'),
            'cost_center_id'      => $cc->id,
            'activity_type_id'    => $at->id,
            'confirmed_quantity'  => 10.0,
            'fiscal_year'         => 2025,
            'period'              => 3,
            'confirmation_date'   => '2025-03-31',
            'confirmed_by'        => $this->user->id,
            'status'              => ActivityConfirmation::STATUS_CONFIRMED,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $cc = $this->makeCostCenter();
        $at = $this->makeActivityType();
        $this->makeConfirmation($cc, $at);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/activity-confirmations');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/activity-confirmations', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_confirmation(): void
    {
        $cc = $this->makeCostCenter();
        $at = $this->makeActivityType();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/activity-confirmations', [
                'cost_center_id'     => $cc->id,
                'activity_type_id'   => $at->id,
                'confirmed_quantity' => 8.0,
                'fiscal_year'        => 2025,
                'period'             => 3,
                'confirmation_date'  => '2025-03-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_details(): void
    {
        $cc   = $this->makeCostCenter();
        $at   = $this->makeActivityType();
        $conf = $this->makeConfirmation($cc, $at);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/activity-confirmations/' . $conf->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/activity-confirmations/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Reverse
    // -------------------------------------------------------------------------

    public function test_reverse_creates_reversal(): void
    {
        $cc   = $this->makeCostCenter();
        $at   = $this->makeActivityType();
        $conf = $this->makeConfirmation($cc, $at);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/activity-confirmations/' . $conf->uuid . '/reverse');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_reverse_rejects_already_reversed(): void
    {
        $cc   = $this->makeCostCenter();
        $at   = $this->makeActivityType();
        $conf = $this->makeConfirmation($cc, $at, ['status' => ActivityConfirmation::STATUS_REVERSED]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/activity-confirmations/' . $conf->uuid . '/reverse');

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/controlling/activity-confirmations')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Filters, tenant isolation and response shape
    // -------------------------------------------------------------------------

    public function test_index_applies_filters_and_excludes_other_organizations(): void
    {
        $cc = $this->makeCostCenter();
        $at = $this->makeActivityType();
        $this->makeConfirmation($cc, $at, ['confirmation_number' => 'CONF-A', 'period' => 3]);
        $this->makeConfirmation($cc, $at, [
            'confirmation_number' => 'CONF-B',
            'period'              => 4,
            'status'              => ActivityConfirmation::STATUS_REVERSED,
        ]);
        $this->makeConfirmation($cc, $at, [
            'confirmation_number' => 'CONF-X',
            'organization_id'     => Organization::factory()->create()->id,
        ]);

        $all = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/activity-confirmations?status=&period=');
        $all->assertStatus(200)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('data.0.cost_center.code', $cc->code)
            ->assertJsonPath('data.0.activity_type.code', $at->code);
        $this->assertCount(2, $all->json('data'));

        $byPeriod = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/activity-confirmations?period=4&cost_center_id=' . $cc->id);
        $this->assertSame(['CONF-B'], array_column($byPeriod->json('data'), 'confirmation_number'));

        $byStatus = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/activity-confirmations?status=confirmed&per_page=10');
        $byStatus->assertJsonPath('meta.per_page', 10);
        $this->assertSame(['CONF-A'], array_column($byStatus->json('data'), 'confirmation_number'));
    }

    public function test_store_derives_cost_and_sets_defaults(): void
    {
        $cc = $this->makeCostCenter();
        $at = $this->makeActivityType();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/activity-confirmations', [
                'cost_center_id'     => $cc->id,
                'activity_type_id'   => $at->id,
                'confirmed_quantity' => 8,
                'actual_rate'        => 12.5,
                'fiscal_year'        => 2025,
                'period'             => 3,
                'confirmation_date'  => '2025-03-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Activity confirmation recorded.')
            ->assertJsonPath('data.status', ActivityConfirmation::STATUS_CONFIRMED)
            ->assertJsonPath('data.confirmed_by', $this->user->id)
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.cost_center.code', $cc->code)
            ->assertJsonPath('data.activity_type.code', $at->code);
        $this->assertEquals(100, $response->json('data.actual_cost'));
        $this->assertStringStartsWith('CONF-', $response->json('data.confirmation_number'));
    }

    public function test_reverse_mirrors_the_original_and_marks_it_reversed(): void
    {
        $cc   = $this->makeCostCenter();
        $at   = $this->makeActivityType();
        $conf = $this->makeConfirmation($cc, $at, ['planned_quantity' => 5, 'actual_cost' => 50]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/activity-confirmations/' . $conf->uuid . '/reverse');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Activity confirmation reversed.')
            ->assertJsonPath('data.reversal_id', $conf->id)
            ->assertJsonPath('data.status', ActivityConfirmation::STATUS_CONFIRMED)
            ->assertJsonPath('data.cost_center.code', $cc->code)
            ->assertJsonPath('data.activity_type.code', $at->code);
        $this->assertEquals(-10, $response->json('data.confirmed_quantity'));
        $this->assertEquals(-5, $response->json('data.planned_quantity'));
        $this->assertEquals(-50, $response->json('data.actual_cost'));
        $this->assertStringStartsWith('REV-', $response->json('data.confirmation_number'));

        $this->assertDatabaseHas('activity_confirmations', [
            'id'          => $conf->id,
            'status'      => ActivityConfirmation::STATUS_REVERSED,
            'reversal_id' => $response->json('data.id'),
        ]);
    }

    public function test_reverse_rejects_already_reversed_with_its_error_code(): void
    {
        $cc   = $this->makeCostCenter();
        $at   = $this->makeActivityType();
        $conf = $this->makeConfirmation($cc, $at, ['status' => ActivityConfirmation::STATUS_REVERSED]);

        $this->withToken($this->token)
            ->postJson('/api/v1/controlling/activity-confirmations/' . $conf->uuid . '/reverse')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_REVERSED')
            ->assertJsonPath('error.message', 'Only confirmed records can be reversed.');
    }

    public function test_reverse_returns_404_for_other_organization_confirmation(): void
    {
        $cc   = $this->makeCostCenter();
        $at   = $this->makeActivityType();
        $conf = $this->makeConfirmation($cc, $at, ['organization_id' => Organization::factory()->create()->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/controlling/activity-confirmations/' . $conf->uuid . '/reverse')
            ->assertStatus(404);

        $this->assertDatabaseCount('activity_confirmations', 1);
    }

    public function test_reverse_rechecks_the_status_on_the_current_row(): void
    {
        $cc    = $this->makeCostCenter();
        $at    = $this->makeActivityType();
        $stale = $this->makeConfirmation($cc, $at);
        ActivityConfirmation::whereKey($stale->id)->update(['status' => ActivityConfirmation::STATUS_REVERSED]);

        try {
            app(ActivityConfirmationService::class)->reverse($stale, $this->user->id);
            $this->fail('A confirmation reversed since it was loaded must not be reversed again.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Only confirmed records can be reversed.', $e->getMessage());
        }

        $this->assertDatabaseCount('activity_confirmations', 1);
    }
}
