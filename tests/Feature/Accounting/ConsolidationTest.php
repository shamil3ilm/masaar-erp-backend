<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\ConsolidationEntity;
use App\Models\Accounting\ConsolidationGroup;
use App\Models\Accounting\ConsolidationPeriod;
use App\Models\Accounting\EliminationEntry;
use App\Models\Accounting\FiscalYear;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ConsolidationTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.consolidation.view',
            'accounting.consolidation.create',
            'accounting.consolidation.edit',
            'accounting.consolidation.delete',
            'accounting.consolidation.complete',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeGroup(array $overrides = []): ConsolidationGroup
    {
        return ConsolidationGroup::create(array_merge([
            'organization_id' => $this->organization->id,
            'name'            => 'Test Group',
            'currency_code'   => 'SAR',
            'is_active'       => true,
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    private function makePeriod(ConsolidationGroup $group, array $overrides = []): ConsolidationPeriod
    {
        return ConsolidationPeriod::create(array_merge([
            'organization_id'         => $this->organization->id,
            'consolidation_group_id'  => $group->id,
            'period_start'            => '2025-01-01',
            'period_end'              => '2025-12-31',
            'status'                  => ConsolidationPeriod::STATUS_OPEN,
            'created_by'              => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Groups — index
    // -------------------------------------------------------------------------

    public function test_index_groups_returns_list(): void
    {
        $this->makeGroup(['name' => 'Group A']);
        $this->makeGroup(['name' => 'Group B']);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/groups');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Groups — store
    // -------------------------------------------------------------------------

    public function test_store_group_creates_group(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/groups', [
                'name'          => 'Gulf Entities',
                'currency_code' => 'SAR',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Gulf Entities');
    }

    public function test_store_group_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/groups', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Groups — show
    // -------------------------------------------------------------------------

    public function test_show_group_returns_details(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/groups/' . $group->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $group->id);
    }

    public function test_show_group_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/groups/99999');

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Groups — update
    // -------------------------------------------------------------------------

    public function test_update_group_modifies_group(): void
    {
        $group = $this->makeGroup(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/consolidation/groups/' . $group->id, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    // -------------------------------------------------------------------------
    // Groups — destroy
    // -------------------------------------------------------------------------

    public function test_destroy_group_deletes_group(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/consolidation/groups/' . $group->id);

        $response->assertStatus(200);
        $this->assertNull(ConsolidationGroup::find($group->id));
    }

    public function test_destroy_group_blocks_if_completed_periods_exist(): void
    {
        $group  = $this->makeGroup();
        $this->makePeriod($group, ['status' => ConsolidationPeriod::STATUS_COMPLETED]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/consolidation/groups/' . $group->id);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Groups — add entity
    // -------------------------------------------------------------------------

    public function test_add_entity_to_group(): void
    {
        $group     = $this->makeGroup();
        $otherOrg  = \App\Models\Core\Organization::factory()->create();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/groups/' . $group->id . '/entities', [
                'entity_organization_id' => $otherOrg->id,
                'name'                   => 'Subsidiary A',
                'ownership_percent'      => 100,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Subsidiary A');
    }

    // -------------------------------------------------------------------------
    // Periods — index
    // -------------------------------------------------------------------------

    public function test_index_periods_returns_list(): void
    {
        $group = $this->makeGroup();
        $this->makePeriod($group);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/periods');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Periods — store
    // -------------------------------------------------------------------------

    public function test_store_period_creates_period(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/periods', [
                'consolidation_group_id' => $group->id,
                'period_start'           => '2025-01-01',
                'period_end'             => '2025-12-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.consolidation_group_id', $group->id);
    }

    public function test_store_period_validates_dates(): void
    {
        $group = $this->makeGroup();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/periods', [
                'consolidation_group_id' => $group->id,
                'period_start'           => '2025-12-31',
                'period_end'             => '2025-01-01', // before start
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Periods — show
    // -------------------------------------------------------------------------

    public function test_show_period_returns_details(): void
    {
        $group  = $this->makeGroup();
        $period = $this->makePeriod($group);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/periods/' . $period->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $period->id);
    }

    // -------------------------------------------------------------------------
    // Periods — collect balances
    // -------------------------------------------------------------------------

    public function test_collect_balances_succeeds_for_open_period(): void
    {
        $group  = $this->makeGroup();
        $period = $this->makePeriod($group);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/periods/' . $period->id . '/collect-balances');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_collect_balances_blocked_for_completed_period(): void
    {
        $group  = $this->makeGroup();
        $period = $this->makePeriod($group, ['status' => ConsolidationPeriod::STATUS_COMPLETED]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/periods/' . $period->id . '/collect-balances');

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Tenant isolation and response contracts
    // -------------------------------------------------------------------------

    private function makeOtherOrganizationPeriod(): ConsolidationPeriod
    {
        $otherOrg   = Organization::factory()->create();
        $otherGroup = ConsolidationGroup::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'Other Group',
            'currency_code'   => 'SAR',
            'is_active'       => true,
        ]);

        return ConsolidationPeriod::create([
            'organization_id'        => $otherOrg->id,
            'consolidation_group_id' => $otherGroup->id,
            'period_start'           => '2025-01-01',
            'period_end'             => '2025-12-31',
            'status'                 => ConsolidationPeriod::STATUS_OPEN,
        ]);
    }

    private function makeElimination(ConsolidationPeriod $period, string $type): EliminationEntry
    {
        $debit  = Account::factory()->create(['organization_id' => $this->organization->id]);
        $credit = Account::factory()->create(['organization_id' => $this->organization->id]);

        return EliminationEntry::create([
            'organization_id'         => $this->organization->id,
            'consolidation_period_id' => $period->id,
            'entry_type'              => $type,
            'description'             => 'Elimination ' . $type,
            'debit_account_id'        => $debit->id,
            'credit_account_id'       => $credit->id,
            'amount'                  => 100,
            'currency_code'           => 'SAR',
            'created_by'              => $this->user->id,
        ]);
    }

    public function test_group_endpoints_return_not_found_for_another_organizations_group(): void
    {
        $otherGroup = $this->makeOtherOrganizationPeriod()->consolidation_group_id;
        $base       = '/api/v1/consolidation/groups/' . $otherGroup;
        $otherOrgId = Organization::factory()->create()->id;

        $responses = [
            $this->withToken($this->token)->getJson($base),
            $this->withToken($this->token)->putJson($base, ['name' => 'Taken']),
            $this->withToken($this->token)->deleteJson($base),
            $this->withToken($this->token)->postJson($base . '/entities', [
                'entity_organization_id' => $otherOrgId,
                'name'                   => 'Sub',
            ]),
        ];

        foreach ($responses as $response) {
            $response->assertStatus(404)
                ->assertJsonPath('error.code', 'NOT_FOUND')
                ->assertJsonPath('error.message', 'Consolidation group not found.');
        }

        $this->assertSame('Other Group', ConsolidationGroup::withoutGlobalScopes()->find($otherGroup)->name);
        $this->assertSame(0, ConsolidationEntity::count());
    }

    public function test_period_endpoints_return_not_found_for_another_organizations_period(): void
    {
        $base = '/api/v1/consolidation/periods/' . $this->makeOtherOrganizationPeriod()->id;

        $responses = [
            $this->withToken($this->token)->getJson($base),
            $this->withToken($this->token)->postJson($base . '/collect-balances'),
            $this->withToken($this->token)->postJson($base . '/complete'),
            $this->withToken($this->token)->getJson($base . '/report'),
            $this->withToken($this->token)->getJson($base . '/eliminations'),
            $this->withToken($this->token)->postJson($base . '/eliminations', []),
            $this->withToken($this->token)->postJson($base . '/generate-eliminations'),
            $this->withToken($this->token)->getJson($base . '/eliminations-auto'),
        ];

        foreach ($responses as $response) {
            $response->assertStatus(404)
                ->assertJsonPath('error.code', 'NOT_FOUND')
                ->assertJsonPath('error.message', 'Consolidation period not found.');
        }
    }

    public function test_remove_entity_deletes_an_entity_of_the_organizations_group(): void
    {
        $group  = $this->makeGroup();
        $entity = ConsolidationEntity::create([
            'consolidation_group_id' => $group->id,
            'entity_organization_id' => Organization::factory()->create()->id,
            'name'                   => 'Subsidiary',
            'ownership_percent'      => 60,
        ]);

        $this->withToken($this->token)
            ->deleteJson('/api/v1/consolidation/entities/' . $entity->id)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Entity removed from consolidation group.');

        $this->assertNull(ConsolidationEntity::find($entity->id));
    }

    public function test_remove_entity_returns_not_found_for_an_entity_of_another_organizations_group(): void
    {
        $otherPeriod = $this->makeOtherOrganizationPeriod();
        $entity      = ConsolidationEntity::create([
            'consolidation_group_id' => $otherPeriod->consolidation_group_id,
            'entity_organization_id' => $otherPeriod->organization_id,
            'name'                   => 'Their subsidiary',
            'ownership_percent'      => 100,
        ]);

        $this->withToken($this->token)
            ->deleteJson('/api/v1/consolidation/entities/' . $entity->id)
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.message', 'Consolidation entity not found.');

        $this->assertNotNull(ConsolidationEntity::find($entity->id));
    }

    public function test_store_period_rejects_another_organizations_group(): void
    {
        $otherPeriod = $this->makeOtherOrganizationPeriod();

        $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/periods', [
                'consolidation_group_id' => $otherPeriod->consolidation_group_id,
                'period_start'           => '2025-01-01',
                'period_end'             => '2025-12-31',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('consolidation_group_id');

        $this->assertSame(0, ConsolidationPeriod::count());
    }

    public function test_store_period_rejects_another_organizations_fiscal_year(): void
    {
        $group     = $this->makeGroup();
        $otherYear = FiscalYear::create([
            'organization_id' => Organization::factory()->create()->id,
            'name'            => 'FY2025 other',
            'start_date'      => '2025-01-01',
            'end_date'        => '2025-12-31',
            'is_closed'       => false,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/periods', [
                'consolidation_group_id' => $group->id,
                'fiscal_year_id'         => $otherYear->id,
                'period_start'           => '2025-01-01',
                'period_end'             => '2025-12-31',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('fiscal_year_id');
    }

    public function test_index_groups_filters_active_groups(): void
    {
        $this->makeGroup(['name' => 'Active']);
        $this->makeGroup(['name' => 'Dormant', 'is_active' => false]);

        $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/groups?active=true&per_page=10')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active')
            ->assertJsonPath('data.0.periods_count', 0)
            ->assertJsonPath('data.0.entities', [])
            ->assertJsonPath('meta.per_page', 10);

        $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/groups')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_index_periods_filters_by_group_and_status(): void
    {
        $groupA = $this->makeGroup(['name' => 'A']);
        $groupB = $this->makeGroup(['name' => 'B']);
        $this->makePeriod($groupA);
        $this->makePeriod($groupA, ['status' => ConsolidationPeriod::STATUS_COMPLETED, 'period_start' => '2024-01-01', 'period_end' => '2024-12-31']);
        $this->makePeriod($groupB);

        $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/periods?group_id=' . $groupA->id . '&status=completed')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.group.name', 'A')
            ->assertJsonPath('data.0.elimination_entries_count', 0)
            ->assertJsonPath('data.0.consolidated_balances_count', 0);
    }

    public function test_show_period_includes_group_entities_and_counts(): void
    {
        $period = $this->makePeriod($this->makeGroup(['name' => 'Gulf']));
        $this->makeElimination($period, 'other');

        $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/periods/' . $period->id)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Consolidation period retrieved.')
            ->assertJsonPath('data.group.name', 'Gulf')
            ->assertJsonPath('data.group.entities', [])
            ->assertJsonPath('data.elimination_entries_count', 1);
    }

    public function test_index_eliminations_lists_the_periods_entries_by_type(): void
    {
        $period = $this->makePeriod($this->makeGroup());
        $other  = $this->makePeriod($this->makeGroup(), ['period_start' => '2024-01-01', 'period_end' => '2024-12-31']);
        $this->makeElimination($period, 'dividend');
        $this->makeElimination($period, 'other');
        $this->makeElimination($other, 'dividend');

        $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/periods/' . $period->id . '/eliminations?entry_type=dividend')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entry_type', 'dividend')
            ->assertJsonPath('data.0.created_by.id', $this->user->id)
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_collect_balances_returns_period_and_collected_count(): void
    {
        $period = $this->makePeriod($this->makeGroup());

        $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/periods/' . $period->id . '/collect-balances')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Entity balances collected successfully.')
            ->assertJsonPath('data.period.status', ConsolidationPeriod::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.balances_collected', 0);
    }

    public function test_complete_period_without_balances_returns_validation_error(): void
    {
        $period = $this->makePeriod($this->makeGroup());

        $this->withToken($this->token)
            ->postJson('/api/v1/consolidation/periods/' . $period->id . '/complete')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_completed_period_blocks_eliminations(): void
    {
        $period = $this->makePeriod($this->makeGroup(), ['status' => ConsolidationPeriod::STATUS_COMPLETED]);
        $base   = '/api/v1/consolidation/periods/' . $period->id;

        $this->withToken($this->token)->postJson($base . '/generate-eliminations')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PERIOD_COMPLETED')
            ->assertJsonPath('error.message', 'Cannot generate eliminations for a completed period.');

        $this->withToken($this->token)->postJson($base . '/eliminations', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PERIOD_COMPLETED')
            ->assertJsonPath('error.message', 'Cannot add elimination entries to a completed period.');
    }

    public function test_store_elimination_and_auto_list_return_entries(): void
    {
        $period = $this->makePeriod($this->makeGroup());
        $debit  = Account::factory()->create(['organization_id' => $this->organization->id]);
        $credit = Account::factory()->create(['organization_id' => $this->organization->id]);
        $base   = '/api/v1/consolidation/periods/' . $period->id;

        $this->withToken($this->token)
            ->postJson($base . '/eliminations', [
                'entry_type'        => 'dividend',
                'description'       => 'Dividend elimination',
                'debit_account_id'  => $debit->id,
                'credit_account_id' => $credit->id,
                'amount'            => 250,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.consolidation_period_id', $period->id)
            ->assertJsonPath('data.debit_account.id', $debit->id);

        $this->withToken($this->token)
            ->getJson($base . '/eliminations-auto')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('message', 'Elimination entries retrieved.');
    }

    public function test_destroy_group_blocked_returns_error_code_and_message(): void
    {
        $group = $this->makeGroup();
        $this->makePeriod($group, ['status' => ConsolidationPeriod::STATUS_COMPLETED]);

        $this->withToken($this->token)
            ->deleteJson('/api/v1/consolidation/groups/' . $group->id)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DELETE_BLOCKED')
            ->assertJsonPath('error.message', 'Cannot delete a group that has completed periods.');
    }

    public function test_update_group_returns_group_with_entities_and_creator(): void
    {
        $group = $this->makeGroup();

        $this->withToken($this->token)
            ->putJson('/api/v1/consolidation/groups/' . $group->id, ['is_active' => false])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Consolidation group updated.')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.entities', [])
            ->assertJsonPath('data.created_by.id', $this->user->id);
    }

    public function test_report_returns_consolidated_report(): void
    {
        $period = $this->makePeriod($this->makeGroup());

        $this->withToken($this->token)
            ->getJson('/api/v1/consolidation/periods/' . $period->id . '/report')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Consolidated report generated.')
            ->assertJsonPath('data.period.id', $period->id);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/consolidation/groups')->assertStatus(401);
        $this->getJson('/api/v1/consolidation/periods')->assertStatus(401);
    }
}
