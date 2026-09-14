<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Accounting\Account;
use App\Models\Accounting\AssetCategory;
use App\Models\Expense\ExpenseCategory;
use App\Models\Finance\PettyCashFund;
use App\Models\HR\Employee;
use App\Models\Inventory\Warehouse;
use App\Models\Maintenance\Equipment;
use App\Models\Manufacturing\CalibrationEquipment;
use App\Models\Manufacturing\CalibrationPlan;
use App\Services\Accounting\AssetAccountingService;
use App\Services\Accounting\DisputeManagementService;
use App\Services\Accounting\PettyCashService;
use App\Services\Expense\ExpenseReportService;
use App\Services\Expense\ExpenseService;
use App\Services\HR\TravelExpenseService;
use App\Services\Inventory\WarehouseTransferOrderService;
use App\Services\Maintenance\MaintenanceService;
use App\Services\Manufacturing\CalibrationService;
use App\Services\TM\TransportationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * The number each document gets when it is created: its prefix, the year (or
 * year and month) and a zero-padded sequence per organization. The second
 * document of each type is created after the first has been renumbered, as a
 * document numbered earlier would be, and continues after that number.
 */
class DocumentNumbersTest extends TestCase
{
    use BuildsLedger, RefreshDatabase, TestHelpers;

    private string $year;

    private ?Migration $numberSequences = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');

        $this->year = now()->format('Y');
    }

    public function test_maintenance_orders(): void
    {
        $equipment = Equipment::create([
            'organization_id' => $this->organization->id,
            'equipment_number' => 'EQ-001',
            'name' => 'Packing line conveyor',
            'status' => Equipment::STATUS_ACTIVE,
        ]);

        $this->assertNumbering(
            fn () => app(MaintenanceService::class)->createMaintenanceOrder([
                'organization_id' => $this->organization->id,
                'equipment_id' => $equipment->id,
                'description' => 'Replace the drive belt',
            ], [], [], $this->user->id),
            'order_number',
            "MO-{$this->year}-000001",
            "MO-{$this->year}-000041",
            "MO-{$this->year}-000042",
        );
    }

    public function test_warehouse_transfer_orders(): void
    {
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertNumbering(
            fn () => app(WarehouseTransferOrderService::class)->create([
                'organization_id' => $this->organization->id,
                'warehouse_id' => $warehouse->id,
            ]),
            'to_number',
            "TO-{$this->year}-000001",
            "TO-{$this->year}-000041",
            "TO-{$this->year}-000042",
        );
    }

    public function test_calibration_orders(): void
    {
        $equipment = CalibrationEquipment::factory()->create(['organization_id' => $this->organization->id]);
        $plan = CalibrationPlan::create([
            'organization_id' => $this->organization->id,
            'calibration_equipment_id' => $equipment->id,
            'plan_code' => 'CP-001',
            'calibration_interval_days' => 90,
        ]);

        $this->assertNumbering(
            fn () => app(CalibrationService::class)->createCalibrationOrder($plan),
            'order_number',
            "CAL-{$this->year}-00001",
            "CAL-{$this->year}-00041",
            "CAL-{$this->year}-00042",
        );
    }

    public function test_fixed_assets(): void
    {
        $category = AssetCategory::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertNumbering(
            fn () => app(AssetAccountingService::class)->createAsset([
                'organization_id' => $this->organization->id,
                'asset_category_id' => $category->id,
                'name' => 'Company laptop',
                'acquisition_date' => now()->toDateString(),
                'acquisition_cost' => 3500,
                'useful_life_years' => 3,
            ], $this->user->id),
            'asset_number',
            "FA-{$this->year}-000001",
            "FA-{$this->year}-000041",
            "FA-{$this->year}-000042",
        );
    }

    public function test_dispute_cases_are_numbered_by_month(): void
    {
        $month = now()->format('Ym');

        $this->assertNumbering(
            fn () => app(DisputeManagementService::class)->openCase([
                'organization_id' => $this->organization->id,
                'document_type' => 'invoice',
                'document_id' => 1,
                'contact_id' => 1,
                'disputed_amount' => 100,
                'created_by' => $this->user->id,
            ]),
            'case_number',
            "DISP-{$month}-0001",
            "DISP-{$month}-0041",
            "DISP-{$month}-0042",
        );
    }

    public function test_travel_requests_and_expense_claims(): void
    {
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);
        $service = app(TravelExpenseService::class);

        $this->assertNumbering(
            fn () => $service->createRequest([
                'organization_id' => $this->organization->id,
                'employee_id' => $employee->id,
                'purpose' => 'Client visit',
                'departure_date' => now()->toDateString(),
                'return_date' => now()->addDays(2)->toDateString(),
                'destination_country' => 'AE',
                'estimated_cost' => 500,
                'created_by' => $this->user->id,
            ]),
            'request_number',
            "TRV-{$this->year}-000001",
            "TRV-{$this->year}-000041",
            "TRV-{$this->year}-000042",
        );

        $this->assertNumbering(
            fn () => $service->createClaim([
                'organization_id' => $this->organization->id,
                'employee_id' => $employee->id,
                'created_by' => $this->user->id,
            ]),
            'claim_number',
            "TEC-{$this->year}-000001",
            "TEC-{$this->year}-000041",
            "TEC-{$this->year}-000042",
        );
    }

    public function test_expenses_and_expense_reports(): void
    {
        $category = ExpenseCategory::factory()->create(['organization_id' => $this->organization->id]);
        $employee = Employee::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertNumbering(
            fn () => app(ExpenseService::class)->create([
                'organization_id' => $this->organization->id,
                'category_id' => $category->id,
                'expense_date' => now()->toDateString(),
                'description' => 'Airport taxi',
                'amount' => 40,
            ], $this->user->id),
            'expense_number',
            "EXP-{$this->year}-000001",
            "EXP-{$this->year}-000041",
            "EXP-{$this->year}-000042",
        );

        $this->assertNumbering(
            fn () => app(ExpenseReportService::class)->create([
                'organization_id' => $this->organization->id,
                'employee_id' => $employee->id,
                'title' => 'Site visit',
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
            ]),
            'report_number',
            "ER-{$this->year}-000001",
            "ER-{$this->year}-000041",
            "ER-{$this->year}-000042",
        );
    }

    public function test_freight_tenders_and_transportation_orders_are_numbered_in_sequence(): void
    {
        $service = app(TransportationService::class);

        $first = $service->createTenderRequest($this->organization->id, ['title' => 'Riyadh to Jeddah lane']);
        $second = $service->createTenderRequest($this->organization->id, ['title' => 'Jeddah to Dammam lane']);
        $order = $service->createTransportationOrder($this->organization->id, []);

        $this->assertSame("freight_tender-{$this->year}-00001", $first->tender_number);
        $this->assertSame("freight_tender-{$this->year}-00002", $second->tender_number);
        $this->assertSame("transport_order-{$this->year}-00001", $order->order_number);
    }

    public function test_petty_cash_vouchers_are_numbered_in_sequence(): void
    {
        $fund = PettyCashFund::create([
            'organization_id' => $this->organization->id,
            'name' => 'Front desk',
            'custodian_id' => $this->user->id,
            'account_id' => $this->ledgerAccount('1050', 'Petty Cash', Account::TYPE_ASSET, Account::SUBTYPE_CASH)->id,
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'currency_code' => 'SAR',
            'is_active' => true,
        ]);
        $service = app(PettyCashService::class);
        $voucher = ['amount' => 25, 'transaction_type' => 'payment', 'description' => 'Postage'];

        $this->assertSame("petty_cash_voucher-{$this->year}-00001", $service->createVoucher($fund, $voucher)->voucher_number);
        $this->assertSame("petty_cash_voucher-{$this->year}-00002", $service->createVoucher($fund, $voucher)->voucher_number);
    }

    /**
     * Creates a document, renumbers it as if it had been numbered earlier, and
     * creates another, which must continue after the renumbered one.
     *
     * @param  callable(): Model  $create
     */
    private function assertNumbering(callable $create, string $column, string $first, string $existing, string $next): void
    {
        $document = $create();
        $this->assertSame($first, $document->fresh()->{$column});

        $document->forceFill([$column => $existing])->saveQuietly();
        $this->continueCountersAfterStoredNumbers();

        $this->assertSame($next, $create()->fresh()->{$column});
    }

    /**
     * Runs the migration a deployment runs, which starts each number counter
     * after the numbers documents already carry.
     */
    private function continueCountersAfterStoredNumbers(): void
    {
        $this->numberSequences ??= require database_path('migrations/0520_continue_number_sequences_after_stored_numbers.php');
        $this->numberSequences->up();
    }
}
