<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Purchase\VendorContract;
use App\Models\Sales\Contact;
use App\Services\Purchase\VendorContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Vendor contract endpoints: the contact and products must be the caller's
 * organization's, the contact's tax number leaves masked, and status changes
 * are checked on the locked contract.
 */
class VendorContractEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const TAX_NUMBER = '300123456700003';

    private string $baseUrl = '/purchase/vendor-contracts';
    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['purchase.contracts.view', 'purchase.contracts.create', 'purchase.contracts.manage']);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => self::TAX_NUMBER,
        ]);
    }

    public function test_store_creates_a_draft_with_its_items(): void
    {
        $this->apiPost($this->baseUrl, [
            'contact_id' => $this->supplier->id,
            'title' => 'Supply',
            'start_date' => now()->toDateString(),
            'items' => [['description' => 'Steel', 'unit_price' => 12.5, 'quantity' => 4]],
        ])->assertCreated()
            ->assertJsonPath('data.status', VendorContract::STATUS_DRAFT)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_store_refuses_another_organizations_contact_and_product(): void
    {
        $other = Organization::factory()->create();

        $this->apiPost($this->baseUrl, [
            'contact_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
            'title' => 'Supply',
            'start_date' => now()->toDateString(),
            'items' => [[
                'product_id' => Product::factory()->create(['organization_id' => $other->id])->id,
                'description' => 'Steel',
                'unit_price' => 1,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['contact_id', 'items.0.product_id']);

        $this->assertSame(0, VendorContract::withoutGlobalScopes()->count());
    }

    public function test_show_masks_the_contacts_tax_number(): void
    {
        $contract = $this->contract($this->organization, $this->supplier);

        $response = $this->apiGet("{$this->baseUrl}/{$contract->id}")
            ->assertOk()
            ->assertJsonPath('data.contact.id', $this->supplier->id)
            ->assertJsonPath('data.contact.tax_number', '***********0003');

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_show_of_another_organizations_contract_is_not_found(): void
    {
        $other = Organization::factory()->create();
        $contract = $this->contract($other, Contact::factory()->supplier()->create(['organization_id' => $other->id]));

        $this->apiGet("{$this->baseUrl}/{$contract->id}")->assertNotFound();
    }

    public function test_terminating_an_active_contract_keeps_the_reason(): void
    {
        $contract = $this->contract($this->organization, $this->supplier);

        $this->apiPost("{$this->baseUrl}/{$contract->id}/activate")->assertOk();

        $this->apiPost("{$this->baseUrl}/{$contract->id}/terminate", ['reason' => 'Supplier exited'])
            ->assertOk()
            ->assertJsonPath('data.status', VendorContract::STATUS_TERMINATED)
            ->assertJsonPath('data.notes', 'Terminated: Supplier exited');

        $this->assertNotNull($contract->fresh()->terminated_at);
    }

    public function test_activating_a_stale_copy_of_a_terminated_contract_is_refused(): void
    {
        $contract = $this->contract($this->organization, $this->supplier);
        $staleCopy = VendorContract::withoutGlobalScopes()->findOrFail($contract->id);
        $service = app(VendorContractService::class);

        $service->activate($contract);
        $service->terminate($contract, 'Ended');

        $this->expectException(RuntimeException::class);

        try {
            $service->activate($staleCopy);
        } finally {
            $this->assertSame(VendorContract::STATUS_TERMINATED, $contract->fresh()->status);
        }
    }

    private function contract(Organization $organization, Contact $supplier): VendorContract
    {
        return VendorContract::create([
            'organization_id' => $organization->id,
            'contact_id' => $supplier->id,
            'contract_number' => 'VCON-'.fake()->unique()->numerify('#####'),
            'title' => 'Supply',
            'start_date' => now()->toDateString(),
            'status' => VendorContract::STATUS_DRAFT,
        ]);
    }
}
