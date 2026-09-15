<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ChecksOwnedReferences;
use Tests\Traits\TestHelpers;

/**
 * Controlling and costing requests accept only the caller's organization's
 * cost centers, profit centers, cost elements, employees and other rows.
 */
class ControllingOwnedReferencesTest extends TestCase
{
    use ChecksOwnedReferences;
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.controlling.activity-confirmation.create',
            'accounting.activity-types.manage',
            'accounting.copa.view',
            'accounting.copa.manage',
            'accounting.controlling.cost-center.create',
            'accounting.controlling.cost-center.update',
            'accounting.controlling.cost-center.assign',
            'accounting.controlling.allocation.create',
            'accounting.cost-elements.manage',
            'accounting.co.post',
            'accounting.controlling.reposting.view',
            'accounting.cost-splitting.manage',
            'accounting.costing-sheets.manage',
            'accounting.internal-orders.manage',
            'accounting.overhead-keys.manage',
            'accounting.controlling.profit-center.create',
            'accounting.controlling.profit-center.update',
            'accounting.profitability-segments.manage',
            'accounting.skf.manage',
            'accounting.transfer-pricing.manage',
        ]);
    }

    public function test_activity_confirmation_references(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/controlling/activity-confirmations', [
            'cost_center_id' => 'cost_centers',
            'activity_type_id' => 'activity_types',
            'work_order_id' => 'work_orders',
            'work_center_id' => 'work_centers',
        ]);
    }

    public function test_activity_type_cost_element_and_rate(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/activity-types', ['cost_element_id' => 'cost_elements']);

        $activityType = $this->ownRow('activity_types');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/activity-types/{$activityType}", ['cost_element_id' => 'cost_elements']);
        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/activity-types/{$activityType}/rates", [
            'cost_center_id' => 'cost_centers',
            'fiscal_year_id' => 'fiscal_years',
        ]);
    }

    public function test_copa_references(): void
    {
        $centers = [
            'fiscal_year_id' => 'fiscal_years',
            'profit_center_id' => 'profit_centers',
            'cost_center_id' => 'cost_centers',
        ];

        $this->assertOnlyOwnRowsAccepted('GET', '/api/v1/copa/profitability', $centers);
        $this->assertOnlyOwnRowsAccepted('GET', '/api/v1/copa/dimension/product', $centers);
        $this->assertOnlyOwnRowsAccepted('GET', '/api/v1/copa/plan-versions', ['fiscal_year_id' => 'fiscal_years']);
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/copa/plan-versions', ['fiscal_year_id' => 'fiscal_years']);
        $this->assertOnlyOwnRowsAccepted('GET', '/api/v1/copa/variance', [
            'fiscal_year_id' => 'fiscal_years',
            'plan_version_id' => 'copa_plan_versions',
            'profit_center_id' => 'profit_centers',
        ]);

        $version = $this->ownRow('copa_plan_versions');
        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/copa/plan-versions/{$version}/items", [
            'lines.*.profit_center_id' => 'profit_centers',
        ]);
    }

    public function test_cost_center_references(): void
    {
        $references = [
            'parent_id' => 'cost_centers',
            'manager_id' => 'employees',
            'department_id' => 'departments',
            'gl_account_id' => 'chart_of_accounts',
        ];

        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/controlling/cost-centers', $references);

        $costCenter = $this->ownRow('cost_centers');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/controlling/cost-centers/{$costCenter}", $references);

        $parent = $this->ownRow('cost_centers');
        $account = $this->ownRow('chart_of_accounts');

        $this->withToken($this->token)
            ->postJson('/api/v1/controlling/cost-centers', [
                'code' => 'CC-SALES',
                'name' => 'Sales Department',
                'parent_id' => $parent,
                'gl_account_id' => $account,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('cost_centers', [
            'organization_id' => $this->organization->id,
            'code' => 'CC-SALES',
            'parent_id' => $parent,
            'gl_account_id' => $account,
        ]);
    }

    public function test_cost_center_assignment_plan_and_allocation(): void
    {
        $costCenter = $this->ownRow('cost_centers');

        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/controlling/cost-centers/{$costCenter}/assign", [
            'employee_id' => 'employees',
            'profit_center_id' => 'profit_centers',
        ]);
        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/controlling/cost-centers/{$costCenter}/plan", [
            'cost_element_id' => 'cost_elements',
        ]);
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/controlling/allocations', [
            'fiscal_year_id' => 'fiscal_years',
            'from_cost_center_id' => 'cost_centers',
            'to_cost_center_id' => 'cost_centers',
        ]);
    }

    public function test_cost_element_account(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/cost-elements', ['gl_account_id' => 'chart_of_accounts']);

        $costElement = $this->ownRow('cost_elements');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/cost-elements/{$costElement}", ['gl_account_id' => 'chart_of_accounts']);
    }

    public function test_reconciliation_cycles(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/co-reconciliation/reconcile-assessment', [
            'assessment_cycle_id' => 'assessment_cycles',
        ]);
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/co-reconciliation/reconcile-distribution', [
            'distribution_cycle_id' => 'distribution_cycles',
        ]);
    }

    public function test_reposting_cost_element(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/controlling/co-repostings', ['cost_element_id' => 'cost_elements']);
    }

    public function test_cost_splitting_rule_references(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/cost-splitting/rules', [
            'cost_center_id' => 'cost_centers',
            'cost_element_id' => 'cost_elements',
        ]);

        $rule = $this->ownRow('cost_splitting_rules');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/cost-splitting/rules/{$rule}", ['cost_element_id' => 'cost_elements']);
    }

    public function test_costing_sheet_row_references(): void
    {
        $sheet = $this->ownRow('costing_sheets');

        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/costing-sheets/{$sheet}/rows", [
            'base_cost_element_id' => 'cost_elements',
            'overhead_key_id' => 'overhead_keys',
            'credit_cost_center_id' => 'cost_centers',
            'credit_cost_element_id' => 'cost_elements',
        ]);
    }

    public function test_internal_order_references(): void
    {
        $references = ['cost_center_id' => 'cost_centers', 'responsible_user_id' => 'users'];

        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/internal-orders', $references);

        $order = $this->ownRow('internal_orders');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/internal-orders/{$order}", $references);
    }

    public function test_overhead_rate_references(): void
    {
        $key = $this->ownRow('overhead_keys');

        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/overhead-keys/{$key}/rates", [
            'cost_center_id' => 'cost_centers',
            'activity_type_id' => 'activity_types',
        ]);
    }

    public function test_profit_center_references(): void
    {
        $references = [
            'parent_id' => 'profit_centers',
            'manager_id' => 'employees',
            'gl_account_id' => 'chart_of_accounts',
        ];

        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/controlling/profit-centers', $references);

        $profitCenter = $this->ownRow('profit_centers');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/controlling/profit-centers/{$profitCenter}", $references);
    }

    public function test_profitability_segment_references(): void
    {
        $references = ['customer_group_id' => 'customer_groups', 'product_id' => 'products'];

        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/profitability-segments', $references);

        $segment = $this->ownRow('profitability_segments');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/profitability-segments/{$segment}", $references);
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/profitability-segments/post-values', [
            'profitability_segment_id' => 'profitability_segments',
            'copa_dimension_id' => 'copa_dimensions',
        ]);
    }

    public function test_statistical_key_figure_value_references(): void
    {
        $figure = $this->ownRow('statistical_key_figures');

        $this->assertOnlyOwnRowsAccepted('POST', "/api/v1/statistical-key-figures/{$figure}/post-value", [
            'cost_center_id' => 'cost_centers',
            'profit_center_id' => 'profit_centers',
        ]);
    }

    public function test_transfer_pricing_references(): void
    {
        $references = [
            'from_profit_center_id' => 'profit_centers',
            'to_profit_center_id' => 'profit_centers',
            'from_cost_center_id' => 'cost_centers',
            'to_cost_center_id' => 'cost_centers',
            'product_id' => 'products',
            'cost_element_id' => 'cost_elements',
        ];

        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/transfer-pricing', $references);

        $price = $this->ownRow('transfer_prices');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/transfer-pricing/{$price}", $references);
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/transfer-pricing/calculate', [
            'product_id' => 'products',
            'from_profit_center_id' => 'profit_centers',
            'to_profit_center_id' => 'profit_centers',
        ]);
    }
}
