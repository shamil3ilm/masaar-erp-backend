<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\Core\Organization;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\BuildsTenantRows;

/**
 * A document number or code one organization uses does not stop another
 * organization using the same one.
 *
 * Each of these identifiers is chosen by a tenant or generated for one, and
 * each carried a unique index over the value alone. Two organizations
 * numbering their own documents from one reach the same number on the same
 * day, and the second write was refused - which also told the caller the value
 * exists somewhere else. The index now carries organization_id, so the value
 * is theirs alone within their organization and free everywhere else.
 */
class TenantIdentifierReuseTest extends TestCase
{
    use BuildsTenantRows;
    use RefreshDatabase;

    /**
     * The identifiers that belong to one organization, as table => column.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function identifiers(): array
    {
        $identifiers = [
            'activity_confirmations' => 'confirmation_number',
            'audit_plans' => 'plan_number',
            'budget_transfers' => 'transfer_number',
            'business_partners' => 'bp_number',
            'capa_8d' => 'capa_number',
            'capa_records' => 'capa_number',
            'carriers' => 'code',
            'cash_sales' => 'cash_sale_number',
            'commission_payments' => 'payment_reference',
            'complaints' => 'complaint_number',
            'cost_center_budget_supplements' => 'supplement_number',
            'cost_reconciliation_runs' => 'run_number',
            'counter_based_orders' => 'order_number',
            'counter_based_plans' => 'plan_number',
            'customer_account_groups' => 'group_code',
            'delivery_documents' => 'delivery_number',
            'email_templates' => 'code',
            'ewm_transfer_orders' => 'to_number',
            'free_goods_conditions' => 'condition_number',
            'fx_forwards' => 'contract_number',
            'goods_issues' => 'gi_number',
            'intercompany_reconciliation_sessions' => 'session_number',
            'maintenance_fault_codes' => 'code',
            'maintenance_notifications' => 'notification_number',
            'maintenance_service_orders' => 'service_order_number',
            'maintenance_task_lists' => 'task_list_number',
            'material_account_groups' => 'group_code',
            'personnel_actions' => 'action_number',
            'pick_documents' => 'pick_number',
            'production_confirmations' => 'confirmation_number',
            'report_definitions' => 'code',
            'scheduling_runs' => 'run_number',
            'service_entry_sheets' => 'ses_number',
            'service_purchase_orders' => 'po_number',
            'service_tickets' => 'ticket_number',
            'shop_floor_papers' => 'paper_number',
            'staging_requests' => 'request_number',
            'supplier_ncr_records' => 'ncr_number',
            'travel_expense_reports' => 'report_number',
            'usage_decisions' => 'decision_number',
            'xbrl_taxonomies' => 'namespace',
        ];

        $cases = [];

        foreach ($identifiers as $table => $column) {
            $cases["{$table}.{$column}"] = [$table, $column];
        }

        return $cases;
    }

    #[DataProvider('identifiers')]
    public function test_two_organizations_hold_the_same_identifier(string $table, string $column): void
    {
        $value = 'SHARED-0001';

        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();

        $first = $this->tenantRow($table, $mine->id, [$column => $value, 'organization_id' => $mine->id]);
        $second = $this->tenantRow($table, $theirs->id, [$column => $value, 'organization_id' => $theirs->id]);

        $this->assertNotSame($first, $second);

        $this->assertDatabaseHas($table, [$column => $value, 'organization_id' => $mine->id]);
        $this->assertDatabaseHas($table, [$column => $value, 'organization_id' => $theirs->id]);
    }

    /**
     * The same value twice in one organization is still one row too many: the
     * index that scopes the value to an organization must still hold within it.
     */
    #[DataProvider('identifiers')]
    public function test_one_organization_holds_the_identifier_once(string $table, string $column): void
    {
        $value = 'SHARED-0002';

        $mine = Organization::factory()->create();
        $row = [$column => $value, 'organization_id' => $mine->id];

        $this->tenantRow($table, $mine->id, $row);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->tenantRow($table, $mine->id, $row);
    }
}
