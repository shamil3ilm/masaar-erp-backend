<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\ServiceEntrySheet;
use App\Models\Purchase\ServicePoLine;
use App\Models\Purchase\ServicePurchaseOrder;
use App\Models\Sales\Contact;
use App\Models\User;
use App\Services\Purchase\ServiceProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Service entry sheet endpoints: sheets list and show with their users, a
 * draft is created with its lines, ids a sheet names must be the caller's
 * organization's or its order's own, the vendor's tax number leaves masked,
 * and reviews are checked on the locked sheet.
 */
class ServiceEntrySheetEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const TAX_NUMBER = '300123456700003';

    private string $baseUrl = '/purchase/service-entry-sheets';
    private Contact $supplier;
    private ServicePurchaseOrder $order;
    private ServicePoLine $orderLine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.ses.view', 'purchase.ses.create', 'purchase.ses.edit',
            'purchase.ses.submit', 'purchase.ses.approve',
        ]);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => self::TAX_NUMBER,
        ]);
        $this->order = $this->order($this->organization, $this->supplier, $this->user);
        $this->orderLine = $this->orderLine($this->order);
    }

    public function test_index_lists_only_this_organizations_sheets_with_masked_vendors(): void
    {
        $this->sheet(ServiceEntrySheet::STATUS_DRAFT);
        $other = Organization::factory()->create();
        $otherSupplier = Contact::factory()->supplier()->create(['organization_id' => $other->id]);
        $otherOrder = $this->order($other, $otherSupplier, User::factory()->create(['organization_id' => $other->id]));
        ServiceEntrySheet::create([
            'organization_id' => $other->id,
            'ses_number' => 'SES-FOREIGN',
            'service_purchase_order_id' => $otherOrder->id,
            'vendor_id' => $otherSupplier->id,
            'service_period_from' => '2026-03-01',
            'service_period_to' => '2026-03-31',
            'description' => 'Cleaning',
            'status' => ServiceEntrySheet::STATUS_DRAFT,
        ]);

        $response = $this->apiGet($this->baseUrl)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.vendor.tax_number', '***********0003');

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_show_returns_the_sheet_with_masked_vendor(): void
    {
        $sheet = $this->sheet(ServiceEntrySheet::STATUS_DRAFT);

        $response = $this->apiGet("{$this->baseUrl}/{$sheet->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $sheet->id)
            ->assertJsonPath('data.vendor.tax_number', '***********0003');

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_store_creates_a_draft_with_its_lines(): void
    {
        $this->apiPost($this->baseUrl, $this->sheetPayload($this->orderLine))
            ->assertCreated()
            ->assertJsonPath('data.status', ServiceEntrySheet::STATUS_DRAFT)
            ->assertJsonPath('data.lines.0.actual_quantity', '2.5000')
            ->assertJsonPath('data.lines.0.actual_price', '10.0000')
            ->assertJsonPath('data.lines.0.total_amount', '25.0000');

        $this->assertNotEmpty(ServiceEntrySheet::withoutGlobalScopes()->sole()->ses_number);
    }

    public function test_store_refuses_another_organizations_order_and_vendor(): void
    {
        $other = Organization::factory()->create();
        $otherSupplier = Contact::factory()->supplier()->create(['organization_id' => $other->id]);
        $otherOrder = $this->order($other, $otherSupplier, User::factory()->create(['organization_id' => $other->id]));

        $this->apiPost($this->baseUrl, array_merge($this->sheetPayload($this->orderLine($otherOrder)), [
            'service_purchase_order_id' => $otherOrder->id,
            'vendor_id' => $otherSupplier->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['service_purchase_order_id', 'vendor_id']);

        $this->assertSame(0, ServiceEntrySheet::withoutGlobalScopes()->count());
    }

    public function test_store_refuses_a_line_of_another_order(): void
    {
        $otherLine = $this->orderLine($this->order($this->organization, $this->supplier, $this->user));

        $this->apiPost($this->baseUrl, $this->sheetPayload($otherLine))
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines.0.service_po_line_id');

        $this->assertSame(0, ServiceEntrySheet::withoutGlobalScopes()->count());
    }

    public function test_a_draft_is_submitted_and_accepted(): void
    {
        $sheet = $this->sheet(ServiceEntrySheet::STATUS_DRAFT);

        $this->apiPost("{$this->baseUrl}/{$sheet->uuid}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', ServiceEntrySheet::STATUS_SUBMITTED)
            ->assertJsonPath('data.submitted_by', $this->user->id);

        $this->apiPost("{$this->baseUrl}/{$sheet->uuid}/review", ['action' => 'accept'])
            ->assertOk()
            ->assertJsonPath('data.status', ServiceEntrySheet::STATUS_APPROVED)
            ->assertJsonPath('data.approved_by', $this->user->id);
    }

    public function test_update_refuses_a_submitted_sheet(): void
    {
        $sheet = $this->sheet(ServiceEntrySheet::STATUS_SUBMITTED);

        $this->apiPut("{$this->baseUrl}/{$sheet->uuid}", ['description' => 'Changed'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS')
            ->assertJsonPath('error.message', 'Only draft service entry sheets can be updated.');
    }

    public function test_approving_a_stale_copy_of_a_rejected_sheet_is_refused(): void
    {
        $sheet = $this->sheet(ServiceEntrySheet::STATUS_SUBMITTED);
        $staleCopy = ServiceEntrySheet::withoutGlobalScopes()->findOrFail($sheet->id);

        $this->apiPost("{$this->baseUrl}/{$sheet->uuid}/review", ['action' => 'reject'])
            ->assertOk()
            ->assertJsonPath('data.status', ServiceEntrySheet::STATUS_REJECTED);

        try {
            app(ServiceProcurementService::class)->approveSES($staleCopy);
            $this->fail('A rejected sheet was approved from a stale copy.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Only submitted service entry sheets can be reviewed.', $e->getMessage());
        }

        $this->assertSame(ServiceEntrySheet::STATUS_REJECTED, $sheet->fresh()->status);
    }

    private function order(Organization $organization, Contact $supplier, User $creator): ServicePurchaseOrder
    {
        return ServicePurchaseOrder::create([
            'organization_id' => $organization->id,
            'po_number' => 'SPO-'.fake()->unique()->numerify('#####'),
            'vendor_id' => $supplier->id,
            'description' => 'Cleaning',
            'total_value' => 100,
            'status' => ServicePurchaseOrder::STATUS_SENT,
            'created_by' => $creator->id,
        ]);
    }

    private function orderLine(ServicePurchaseOrder $order): ServicePoLine
    {
        return ServicePoLine::create([
            'service_purchase_order_id' => $order->id,
            'line_number' => 1,
            'service_description' => 'Cleaning',
            'quantity' => 10,
            'uom' => 'HR',
            'unit_price' => 10,
            'total_price' => 100,
        ]);
    }

    private function sheet(string $status): ServiceEntrySheet
    {
        return ServiceEntrySheet::create([
            'organization_id' => $this->organization->id,
            'ses_number' => 'SES-'.fake()->unique()->numerify('#####'),
            'service_purchase_order_id' => $this->order->id,
            'vendor_id' => $this->supplier->id,
            'service_period_from' => '2026-03-01',
            'service_period_to' => '2026-03-31',
            'description' => 'Cleaning',
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sheetPayload(ServicePoLine $line): array
    {
        return [
            'service_purchase_order_id' => $this->order->id,
            'vendor_id' => $this->supplier->id,
            'service_period_from' => '2026-03-01',
            'service_period_to' => '2026-03-31',
            'lines' => [[
                'service_po_line_id' => $line->id,
                'quantity' => 2.5,
                'unit_price' => 10,
            ]],
        ];
    }
}
