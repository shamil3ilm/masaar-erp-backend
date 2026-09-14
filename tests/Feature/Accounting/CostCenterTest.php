<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\CostAllocation;
use App\Models\Accounting\CostCenter;
use App\Models\Core\Organization;
use App\Models\HR\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class CostCenterTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.controlling.cost-center.view',
            'accounting.controlling.cost-center.create',
            'accounting.controlling.cost-center.update',
            'accounting.controlling.cost-center.delete',
            'accounting.controlling.cost-center.assign',
            'accounting.controlling.allocation.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCostCenter(array $overrides = []): CostCenter
    {
        return CostCenter::create(array_merge([
            'organization_id' => $this->organization->id,
            'code'            => 'CC-' . fake()->unique()->numerify('####'),
            'name'            => 'Test Cost Center',
            'status'          => CostCenter::STATUS_ACTIVE,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_list(): void
    {
        $this->makeCostCenter();
        $this->makeCostCenter();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_for_new_org(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_creates_cost_center(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/cost-centers', [
                'code' => 'CC-SALES',
                'name' => 'Sales Department',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'CC-SALES');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/cost-centers', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_cost_center_details(): void
    {
        $cc = $this->makeCostCenter();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers/' . $cc->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $cc->id);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_cost_center(): void
    {
        $cc = $this->makeCostCenter(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/controlling/cost-centers/' . $cc->uuid, [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes_cost_center(): void
    {
        $cc = $this->makeCostCenter();

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/controlling/cost-centers/' . $cc->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('cost_centers', ['id' => $cc->id]);
    }

    // -------------------------------------------------------------------------
    // Deactivate
    // -------------------------------------------------------------------------

    public function test_deactivate_marks_as_inactive(): void
    {
        $cc = $this->makeCostCenter();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/controlling/cost-centers/' . $cc->uuid . '/deactivate');

        $response->assertStatus(200);
        $this->assertEquals(CostCenter::STATUS_INACTIVE, $cc->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // Hierarchy tree
    // -------------------------------------------------------------------------

    public function test_hierarchy_tree_returns_nested_structure(): void
    {
        $parent = $this->makeCostCenter(['name' => 'Parent CC']);
        $this->makeCostCenter(['name' => 'Child CC', 'parent_id' => $parent->id]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers/hierarchy-tree');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Report
    // -------------------------------------------------------------------------

    public function test_report_all_returns_data(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers/report?from=2025-01-01&to=2025-12-31');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_report_validates_required_dates(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers/report');

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Index filters, AG Grid and tenant isolation
    // -------------------------------------------------------------------------

    public function test_index_filters_and_excludes_other_organizations(): void
    {
        $root = $this->makeCostCenter(['code' => 'CC-ROOT', 'name' => 'Root']);
        $this->makeCostCenter(['code' => 'CC-CHILD', 'name' => 'Child', 'parent_id' => $root->id]);
        $this->makeCostCenter(['code' => 'CC-OFF', 'name' => 'Dormant', 'status' => CostCenter::STATUS_INACTIVE]);

        $otherOrg = Organization::factory()->create();
        $this->makeCostCenter(['organization_id' => $otherOrg->id, 'code' => 'CC-OTHER', 'name' => 'Other']);

        $codes = fn (string $query): array => array_column(
            $this->withToken($this->token)->getJson('/api/v1/controlling/cost-centers' . $query)->assertStatus(200)->json('data'),
            'code'
        );

        $this->assertSame(['CC-CHILD', 'CC-OFF', 'CC-ROOT'], $codes(''));
        $this->assertSame(['CC-OFF'], $codes('?status=' . CostCenter::STATUS_INACTIVE));
        $this->assertSame(['CC-CHILD'], $codes('?search=Child'));
        $this->assertSame(['CC-OFF', 'CC-ROOT'], $codes('?roots_only=1'));
        $this->assertSame(['CC-CHILD'], $codes('?roots_only=1&parent_id=' . $root->id));

        $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers?per_page=1')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_answers_ag_grid_requests_with_the_same_filters(): void
    {
        $root = $this->makeCostCenter(['code' => 'CC-A']);
        $this->makeCostCenter(['code' => 'CC-B']);
        $this->makeCostCenter(['code' => 'CC-A-1', 'parent_id' => $root->id]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers?startRow=0&endRow=10&roots_only=1');

        $response->assertStatus(200)
            ->assertJsonPath('rowCount', 2)
            ->assertJsonPath('rows.0.code', 'CC-A')
            ->assertJsonPath('rows.1.code', 'CC-B');
    }

    // -------------------------------------------------------------------------
    // Hierarchy tree shape and query count
    // -------------------------------------------------------------------------

    public function test_hierarchy_tree_nests_active_descendants_in_code_order(): void
    {
        $root  = $this->makeCostCenter(['code' => 'CC-1', 'name' => 'Root']);
        $child = $this->makeCostCenter(['code' => 'CC-1-1', 'parent_id' => $root->id]);
        $this->makeCostCenter(['code' => 'CC-1-1-1', 'parent_id' => $child->id]);
        $this->makeCostCenter(['code' => 'CC-1-2', 'parent_id' => $root->id, 'status' => CostCenter::STATUS_INACTIVE]);
        $this->makeCostCenter(['code' => 'CC-2']);

        $otherOrg = Organization::factory()->create();
        $this->makeCostCenter(['organization_id' => $otherOrg->id, 'code' => 'CC-0', 'parent_id' => null]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers/hierarchy-tree');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Cost center standard hierarchy retrieved')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'CC-1')
            ->assertJsonPath('data.0.uuid', $root->uuid)
            ->assertJsonPath('data.0.manager', null)
            ->assertJsonCount(1, 'data.0.children')
            ->assertJsonPath('data.0.children.0.code', 'CC-1-1')
            ->assertJsonPath('data.0.children.0.children.0.code', 'CC-1-1-1')
            ->assertJsonPath('data.0.children.0.children.0.children', [])
            ->assertJsonPath('data.1.code', 'CC-2')
            ->assertJsonPath('data.1.children', []);

        $this->assertSame(['id', 'uuid', 'code', 'name', 'manager', 'children'], array_keys($response->json('data.0')));
    }

    public function test_hierarchy_tree_reads_cost_centers_in_one_query_whatever_the_tree_size(): void
    {
        $root = $this->makeCostCenter(['code' => 'CC-ROOT']);
        foreach (range(1, 5) as $n) {
            $this->makeCostCenter(['code' => 'CC-ROOT-' . $n, 'parent_id' => $root->id]);
        }

        $costCenterQueries = 0;
        DB::listen(function ($query) use (&$costCenterQueries): void {
            if (preg_match('/from\s+[`"]?cost_centers[`"]?/i', $query->sql) === 1) {
                $costCenterQueries++;
            }
        });

        $this->withToken($this->token)
            ->getJson('/api/v1/controlling/cost-centers/hierarchy-tree')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data.0.children');

        $this->assertSame(1, $costCenterQueries);
    }

    // -------------------------------------------------------------------------
    // Assignments
    // -------------------------------------------------------------------------

    public function test_assign_creates_an_assignment_for_an_employee_of_the_organization(): void
    {
        $costCenter = $this->makeCostCenter(['code' => 'CC-ASSIGN']);
        $employee   = Employee::factory()->create(['organization_id' => $this->organization->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/controlling/cost-centers/' . $costCenter->uuid . '/assign', [
                'employee_id'    => $employee->id,
                'effective_from' => '2025-01-01',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.assignable_id', $employee->id)
            ->assertJsonPath('data.cost_center.code', 'CC-ASSIGN');
    }

    public function test_assign_returns_404_for_another_organizations_employee(): void
    {
        $costCenter = $this->makeCostCenter();
        $otherOrg   = Organization::factory()->create();
        $employee   = Employee::factory()->create(['organization_id' => $otherOrg->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/controlling/cost-centers/' . $costCenter->uuid . '/assign', [
                'employee_id'    => $employee->id,
                'effective_from' => '2025-01-01',
            ])
            ->assertStatus(404);

        $this->assertDatabaseMissing('cost_center_assignments', ['assignable_id' => $employee->id]);
    }

    // -------------------------------------------------------------------------
    // Allocations list
    // -------------------------------------------------------------------------

    public function test_allocations_index_filters_by_status_and_cost_centers(): void
    {
        $a = $this->makeCostCenter(['code' => 'CC-A']);
        $b = $this->makeCostCenter(['code' => 'CC-B']);
        $c = $this->makeCostCenter(['code' => 'CC-C']);

        $first  = $this->makeAllocation($a, $b, CostAllocation::STATUS_DRAFT, '2025-01-31');
        $second = $this->makeAllocation($a, $c, CostAllocation::STATUS_POSTED, '2025-02-28');
        $third  = $this->makeAllocation($b, $c, CostAllocation::STATUS_DRAFT, '2025-03-31');

        $ids = fn (string $query): array => array_column(
            $this->withToken($this->token)->getJson('/api/v1/controlling/allocations' . $query)->assertStatus(200)->json('data'),
            'id'
        );

        $this->assertSame([$third->id, $second->id, $first->id], $ids(''));
        $this->assertSame([$third->id, $first->id], $ids('?status=' . CostAllocation::STATUS_DRAFT));
        $this->assertSame([$second->id, $first->id], $ids('?from_cost_center_id=' . $a->id));
        $this->assertSame([$third->id, $second->id], $ids('?to_cost_center_id=' . $c->id));

        $this->withToken($this->token)
            ->getJson('/api/v1/controlling/allocations')
            ->assertJsonPath('data.0.from_cost_center.code', 'CC-B')
            ->assertJsonPath('meta.per_page', 20);
    }

    private function makeAllocation(CostCenter $from, CostCenter $to, string $status, string $periodEnd): CostAllocation
    {
        return CostAllocation::create([
            'organization_id'     => $this->organization->id,
            'period_start'        => substr($periodEnd, 0, 8) . '01',
            'period_end'          => $periodEnd,
            'from_cost_center_id' => $from->id,
            'to_cost_center_id'   => $to->id,
            'allocation_method'   => CostAllocation::METHOD_PERCENTAGE,
            'allocation_percent'  => 50,
            'status'              => $status,
        ]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/controlling/cost-centers')->assertStatus(401);
    }
}
