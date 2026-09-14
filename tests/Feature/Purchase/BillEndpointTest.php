<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\Bill;
use App\Models\Purchase\BillLine;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use App\Services\Purchase\BillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Bill endpoints not pinned elsewhere: the summary, refusing another
 * organization's purchase order, and deleting a draft atomically on the
 * locked row.
 */
class BillEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/purchase/bills';
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'purchase.bills.view', 'purchase.bills.create', 'purchase.bills.delete',
        ]);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_summary_counts_only_this_organizations_bills(): void
    {
        $this->bill(['status' => Bill::STATUS_DRAFT]);
        $this->bill(['status' => Bill::STATUS_APPROVED, 'total' => 300, 'amount_due' => 300]);

        $other = Organization::factory()->create();
        Bill::factory()->create([
            'organization_id' => $other->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
            'status' => Bill::STATUS_APPROVED,
        ]);

        $response = $this->apiGet("{$this->baseUrl}/summary");

        $response->assertOk()->assertJsonPath('data.total_count', 2)
            ->assertJsonPath('data.draft_count', 1)
            ->assertJsonStructure(['data' => [
                'total_count', 'draft_count', 'unpaid_count', 'overdue_count', 'unpaid_value', 'overdue_value',
            ]]);
    }

    public function test_summary_filters_by_supplier(): void
    {
        $this->bill();
        $this->bill(['supplier_id' => Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
        ])->id]);

        $this->apiGet("{$this->baseUrl}/summary?supplier_id={$this->supplier->id}")
            ->assertOk()->assertJsonPath('data.total_count', 1);
    }

    public function test_store_refuses_another_organizations_purchase_order(): void
    {
        $response = $this->apiPost($this->baseUrl, [
            'supplier_id' => $this->supplier->id,
            'purchase_order_id' => $this->foreignPurchaseOrder()->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'lines' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 10]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('purchase_order_id');
        $this->assertSame(0, Bill::count());
    }

    public function test_create_from_purchase_order_refuses_another_organizations_order(): void
    {
        $response = $this->apiPost("{$this->baseUrl}/from-purchase-order", [
            'purchase_order_id' => $this->foreignPurchaseOrder()->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('purchase_order_id');
    }

    public function test_deleting_a_draft_removes_its_lines(): void
    {
        $bill = $this->bill();
        BillLine::factory()->create(['bill_id' => $bill->id]);

        $this->apiDelete("{$this->baseUrl}/{$bill->id}")->assertOk();

        $this->assertSoftDeleted('bills', ['id' => $bill->id]);
        $this->assertSame(0, BillLine::where('bill_id', $bill->id)->count());
    }

    public function test_a_stale_draft_approved_meanwhile_is_not_deleted(): void
    {
        $this->actingAs($this->user, 'api');
        $bill = $this->bill();
        $stale = Bill::findOrFail($bill->id);

        Bill::whereKey($bill->id)->update(['status' => Bill::STATUS_APPROVED]);

        try {
            app(BillService::class)->delete($stale);
            $this->fail('A bill approved meanwhile was deleted.');
        } catch (\InvalidArgumentException) {
            $this->assertNotSoftDeleted('bills', ['id' => $bill->id]);
        }
    }

    private function bill(array $overrides = []): Bill
    {
        return Bill::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'status' => Bill::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    private function foreignPurchaseOrder(): PurchaseOrder
    {
        $other = Organization::factory()->create();

        return PurchaseOrder::factory()->confirmed()->create([
            'organization_id' => $other->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
        ]);
    }
}
