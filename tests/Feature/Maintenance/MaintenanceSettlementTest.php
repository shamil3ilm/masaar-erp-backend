<?php

declare(strict_types=1);

namespace Tests\Feature\Maintenance;

use App\Models\Accounting\Account;
use App\Models\Accounting\AssetCategory;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\JournalEntry;
use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use App\Models\HR\Employee;
use App\Models\Maintenance\Equipment;
use App\Models\Maintenance\MaintenanceOrder;
use App\Models\Maintenance\MaintenanceOrderCostLine;
use App\Models\Maintenance\MaintenanceOrderSettlement;
use App\Models\Sales\Contact;
use App\Models\System\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * Pins the cost line list, keeps cost lines and settlements on the caller's
 * own orders and receivers, and settles an order's costs once, through a
 * balanced journal entry posted with the settlement rows.
 */
class MaintenanceSettlementTest extends TestCase
{
    use BuildsLedger, RefreshDatabase, TestHelpers;

    private MaintenanceOrder $order;

    private CostCenter $costCenter;

    private Account $expense;

    private Account $capitalization;

    private Account $clearing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['maintenance.costs.view', 'maintenance.costs.manage']);
        $this->setUpOpenFiscalPeriod();
        OrganizationModule::create([
            'organization_id' => $this->organization->id,
            'module_code' => 'maintenance',
            'is_enabled' => true,
        ]);

        $this->order = $this->orderFor($this->organization);
        $this->costCenter = CostCenter::factory()->create(['organization_id' => $this->organization->id]);

        $this->expense = $this->ledgerAccount('6400', 'Maintenance expense', Account::TYPE_EXPENSE, Account::SUBTYPE_OPERATING_EXPENSE);
        $this->capitalization = $this->ledgerAccount('1590', 'Capitalized maintenance', Account::TYPE_ASSET, Account::SUBTYPE_FIXED_ASSET);
        $this->clearing = $this->ledgerAccount('2390', 'Maintenance clearing', Account::TYPE_LIABILITY, Account::SUBTYPE_OTHER_LIABILITY);
        Setting::set('accounting', 'maintenance_expense_account_id', $this->expense->id, null, $this->organization->id);
        Setting::set('accounting', 'maintenance_capitalization_account_id', $this->capitalization->id, null, $this->organization->id);
        Setting::set('accounting', 'maintenance_clearing_account_id', $this->clearing->id, null, $this->organization->id);
    }

    public function test_cost_lines_list_in_posting_date_order_and_show_the_vendor_by_reference(): void
    {
        $vendor = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_SUPPLIER,
            'tax_number' => '300000000000003',
        ]);
        $later = $this->costLine(100, ['posting_date' => now()->toDateString(), 'vendor_id' => $vendor->id]);
        $earlier = $this->costLine(50, ['posting_date' => now()->subDay()->toDateString()]);
        $this->costLine(70, ['maintenance_order_id' => $this->orderFor($this->organization)->id]);

        $response = $this->apiGet("/maintenance/maintenance-orders/{$this->order->id}/costs?per_page=10");

        $response->assertOk()->assertJsonPath('meta.per_page', 10)->assertJsonPath('meta.total', 2);
        $this->assertSame([$earlier->id, $later->id], array_column($response->json('data'), 'id'));
        $this->assertSame($vendor->contact_name, $response->json('data.1.vendor.contact_name'));
        $this->assertArrayNotHasKey('tax_number', $response->json('data.1.vendor'));
    }

    public function test_a_cost_line_shows_the_employee_by_reference(): void
    {
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->apiPost("/maintenance/maintenance-orders/{$this->order->id}/costs", [
            'cost_type' => 'labor',
            'quantity' => 4,
            'unit_cost' => 25,
            'currency_code' => 'SAR',
            'employee_id' => $employee->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.total_cost', '100.0000');
        $this->assertSame($employee->employee_number, $response->json('data.employee.employee_number'));
        $this->assertArrayNotHasKey('date_of_birth', $response->json('data.employee'));
        $this->assertArrayNotHasKey('email', $response->json('data.employee'));
    }

    public function test_a_cost_line_needs_a_total_or_a_quantity_and_unit_cost(): void
    {
        $this->apiPost("/maintenance/maintenance-orders/{$this->order->id}/costs", [
            'cost_type' => 'labor',
            'currency_code' => 'SAR',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_a_cost_line_for_another_organizations_vendor_or_employee_is_refused(): void
    {
        $other = Organization::factory()->create();
        $vendor = Contact::factory()->create(['organization_id' => $other->id]);
        $employee = Employee::factory()->create(['organization_id' => $other->id]);

        $this->apiPost("/maintenance/maintenance-orders/{$this->order->id}/costs", [
            'cost_type' => 'external',
            'total_cost' => 100,
            'currency_code' => 'SAR',
            'vendor_id' => $vendor->id,
            'employee_id' => $employee->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['vendor_id', 'employee_id']);

        $this->assertSame(0, MaintenanceOrderCostLine::withoutGlobalScopes()->count());
    }

    public function test_another_organizations_order_is_not_found_for_costs_or_settlement(): void
    {
        $theirs = $this->orderFor(Organization::factory()->create());
        $base = "/maintenance/maintenance-orders/{$theirs->id}/costs";

        $this->apiGet($base)->assertNotFound();
        $this->apiGet("{$base}/total")->assertNotFound();
        $this->apiGet("{$base}/settlement-history")->assertNotFound();
        $this->apiPost($base, ['cost_type' => 'labor', 'total_cost' => 10, 'currency_code' => 'SAR'])->assertNotFound();
        $this->apiPost("{$base}/settle", $this->fullRule())->assertNotFound();

        $this->assertSame(0, MaintenanceOrderCostLine::withoutGlobalScopes()->count());
        $this->assertSame(0, MaintenanceOrderSettlement::withoutGlobalScopes()->count());
    }

    public function test_the_total_breaks_the_costs_down_by_type(): void
    {
        $this->costLine(100, ['cost_type' => 'labor']);
        $this->costLine(40, ['cost_type' => 'labor']);
        $this->costLine(60, ['cost_type' => 'material']);

        $this->apiGet("/maintenance/maintenance-orders/{$this->order->id}/costs/total")
            ->assertOk()
            ->assertJsonPath('data.maintenance_order_id', $this->order->id)
            ->assertJsonPath('data.by_type.labor', 140)
            ->assertJsonPath('data.by_type.material', 60)
            ->assertJsonPath('data.total', 200);
    }

    public function test_settlement_posts_a_balanced_entry_from_the_orders_costs(): void
    {
        $this->costLine(600);
        $this->costLine(400);

        $response = $this->apiPost("/maintenance/maintenance-orders/{$this->order->id}/costs/settle", ['rules' => [
            ['receiver_type' => 'cost_center', 'receiver_id' => $this->costCenter->id, 'percentage' => 60],
            ['receiver_type' => 'asset', 'receiver_id' => $this->fixedAssetId(), 'percentage' => 40],
        ]]);

        $response->assertCreated();
        $this->assertCount(2, $response->json('data'));

        $entry = JournalEntry::where('source_type', MaintenanceOrder::class)
            ->where('source_id', $this->order->id)
            ->with('lines')
            ->sole();

        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertCount(3, $entry->lines);
        $expenseLine = $entry->lines->firstWhere('account_id', $this->expense->id);
        $this->assertEquals(600, (float) $expenseLine->debit);
        $this->assertSame($this->costCenter->id, (int) $expenseLine->cost_center_id);
        $this->assertEquals(400, (float) $entry->lines->firstWhere('account_id', $this->capitalization->id)->debit);
        $this->assertEquals(1000, (float) $entry->lines->firstWhere('account_id', $this->clearing->id)->credit);

        $settlements = MaintenanceOrderSettlement::orderBy('id')->get();
        $this->assertEquals([600, 400], $settlements->pluck('settled_amount')->map(fn ($amount) => (float) $amount)->all());
        $this->assertSame([$entry->id, $entry->id], $settlements->pluck('journal_entry_id')->all());
        $this->assertSame(MaintenanceOrderSettlement::RULE_PARTIAL, $settlements->first()->settlement_rule_type);
    }

    public function test_settling_again_settles_only_the_costs_added_since(): void
    {
        $this->costLine(1000);
        $settle = "/maintenance/maintenance-orders/{$this->order->id}/costs/settle";

        $this->apiPost($settle, $this->fullRule())->assertCreated();
        $this->apiPost($settle, $this->fullRule())->assertStatus(422)->assertJsonPath('error.code', 'SETTLEMENT_REFUSED');

        $this->costLine(250);
        $this->apiPost($settle, $this->fullRule())->assertCreated()->assertJsonPath('data.0.settled_amount', '250.0000');

        $this->assertSame(2, JournalEntry::where('source_type', MaintenanceOrder::class)->count());
        $this->assertEquals(1250, (float) MaintenanceOrderSettlement::sum('settled_amount'));
    }

    public function test_settlement_is_refused_when_its_accounts_are_not_mapped(): void
    {
        $this->costLine(500);
        Setting::set('accounting', 'maintenance_clearing_account_id', null, null, $this->organization->id);

        $this->apiPost("/maintenance/maintenance-orders/{$this->order->id}/costs/settle", $this->fullRule())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SETTLEMENT_REFUSED');

        $this->assertSame(0, MaintenanceOrderSettlement::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_settlement_records_nothing_when_its_entry_cannot_be_posted(): void
    {
        $this->costLine(500);
        $this->closeFiscalYear();

        $this->apiPost("/maintenance/maintenance-orders/{$this->order->id}/costs/settle", $this->fullRule())
            ->assertStatus(422);

        $this->assertSame(0, MaintenanceOrderSettlement::count());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_settlement_to_another_organizations_receiver_is_refused(): void
    {
        $this->costLine(500);
        $theirs = CostCenter::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPost("/maintenance/maintenance-orders/{$this->order->id}/costs/settle", ['rules' => [
            ['receiver_type' => 'cost_center', 'receiver_id' => $theirs->id, 'percentage' => 100],
        ]])->assertStatus(422)->assertJsonValidationErrors(['rules.0.receiver_id']);

        $this->assertSame(0, MaintenanceOrderSettlement::count());
    }

    public function test_settlement_rule_percentages_must_add_up_to_one_hundred(): void
    {
        $this->costLine(500);

        $this->apiPost("/maintenance/maintenance-orders/{$this->order->id}/costs/settle", ['rules' => [
            ['receiver_type' => 'cost_center', 'receiver_id' => $this->costCenter->id, 'percentage' => 60],
        ]])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_PERCENTAGE');
    }

    public function test_history_lists_the_orders_settlements(): void
    {
        $this->costLine(300);
        $this->apiPost("/maintenance/maintenance-orders/{$this->order->id}/costs/settle", $this->fullRule())->assertCreated();

        $this->apiGet("/maintenance/maintenance-orders/{$this->order->id}/costs/settlement-history")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.settled_amount', '300.0000')
            ->assertJsonPath('data.0.creator.id', $this->user->id);
    }

    /** @return array{rules: list<array<string, mixed>>} */
    private function fullRule(): array
    {
        return ['rules' => [
            ['receiver_type' => 'cost_center', 'receiver_id' => $this->costCenter->id, 'percentage' => 100],
        ]];
    }

    private function orderFor(Organization $organization): MaintenanceOrder
    {
        return MaintenanceOrder::factory()->create([
            'organization_id' => $organization->id,
            'equipment_id' => Equipment::factory()->create(['organization_id' => $organization->id])->id,
        ]);
    }

    private function costLine(float $total, array $overrides = []): MaintenanceOrderCostLine
    {
        return MaintenanceOrderCostLine::create(array_merge([
            'organization_id' => $this->organization->id,
            'maintenance_order_id' => $this->order->id,
            'cost_type' => 'labor',
            'total_cost' => $total,
            'currency_code' => 'SAR',
            'posting_date' => now()->toDateString(),
        ], $overrides));
    }

    private function fixedAssetId(): int
    {
        return FixedAsset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_category_id' => AssetCategory::factory()->create(['organization_id' => $this->organization->id])->id,
        ])->id;
    }
}
