<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Purchase\SaDeliverySchedule;
use App\Models\Purchase\SchedulingAgreement;
use App\Models\Sales\Contact;
use App\Services\Purchase\SchedulingAgreementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Scheduling agreement endpoints: the vendor and product must be the caller's
 * organization's, the vendor's tax number leaves masked, and a delivery is
 * counted on the locked schedule line.
 */
class SchedulingAgreementEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const TAX_NUMBER = '300123456700003';

    private string $baseUrl = '/purchase/scheduling-agreements';
    private Contact $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['purchase.scheduling-agreements.view', 'purchase.scheduling-agreements.manage']);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => self::TAX_NUMBER,
        ]);
        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_index_lists_only_this_organizations_agreements_with_masked_vendors(): void
    {
        $this->agreement($this->organization, $this->supplier, $this->product);
        $other = Organization::factory()->create();
        $this->agreement(
            $other,
            Contact::factory()->supplier()->create(['organization_id' => $other->id]),
            Product::factory()->create(['organization_id' => $other->id]),
        );

        $response = $this->apiGet($this->baseUrl)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.vendor.tax_number', '***********0003');

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_store_refuses_another_organizations_vendor_and_product(): void
    {
        $other = Organization::factory()->create();

        $this->apiPost($this->baseUrl, [
            'vendor_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
            'product_id' => Product::factory()->create(['organization_id' => $other->id])->id,
            'agreement_number' => 'SA-1',
            'valid_from' => now()->toDateString(),
            'target_quantity' => 100,
            'unit_price' => 5,
        ])->assertStatus(422)->assertJsonValidationErrors(['vendor_id', 'product_id']);

        $this->assertSame(0, SchedulingAgreement::withoutGlobalScopes()->count());
    }

    public function test_show_masks_the_vendors_tax_number(): void
    {
        $agreement = $this->agreement($this->organization, $this->supplier, $this->product);

        $response = $this->apiGet("{$this->baseUrl}/{$agreement->id}")
            ->assertOk()
            ->assertJsonPath('data.vendor.tax_number', '***********0003');

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_receiving_a_delivery_updates_the_line_and_the_agreement(): void
    {
        $agreement = $this->agreement($this->organization, $this->supplier, $this->product);
        $line = $this->line($agreement, 10);

        $this->apiPost("{$this->baseUrl}/{$agreement->id}/schedule/{$line->id}/receive", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.received_quantity', '4.0000')
            ->assertJsonPath('data.status', SaDeliverySchedule::STATUS_PARTIAL);

        $this->assertSame('4.0000', $agreement->fresh()->released_quantity);
    }

    public function test_receiving_on_a_line_of_another_agreement_is_not_found(): void
    {
        $agreement = $this->agreement($this->organization, $this->supplier, $this->product);
        $otherLine = $this->line($this->agreement($this->organization, $this->supplier, $this->product), 10);

        $this->apiPost("{$this->baseUrl}/{$agreement->id}/schedule/{$otherLine->id}/receive", ['quantity' => 4])
            ->assertNotFound();

        $this->assertSame('0.0000', $otherLine->fresh()->received_quantity);
    }

    public function test_a_delivery_received_on_a_stale_copy_of_the_line_is_still_counted(): void
    {
        $agreement = $this->agreement($this->organization, $this->supplier, $this->product);
        $line = $this->line($agreement, 10);
        $staleCopy = SaDeliverySchedule::withoutGlobalScopes()->findOrFail($line->id);
        $service = app(SchedulingAgreementService::class);

        $service->receiveDelivery($line, 4);
        $service->receiveDelivery($staleCopy, 6);

        $line->refresh();

        $this->assertSame('10.0000', $line->received_quantity);
        $this->assertSame(SaDeliverySchedule::STATUS_COMPLETE, $line->status);
        $this->assertSame('10.0000', $agreement->fresh()->released_quantity);
    }

    private function agreement(Organization $organization, Contact $supplier, Product $product): SchedulingAgreement
    {
        return SchedulingAgreement::create([
            'organization_id' => $organization->id,
            'vendor_id' => $supplier->id,
            'product_id' => $product->id,
            'agreement_number' => 'SA-'.fake()->unique()->numerify('#####'),
            'status' => SchedulingAgreement::STATUS_ACTIVE,
            'valid_from' => now()->toDateString(),
            'target_quantity' => 100,
            'released_quantity' => 0,
            'unit_price' => 5,
        ]);
    }

    private function line(SchedulingAgreement $agreement, float $quantity): SaDeliverySchedule
    {
        return SaDeliverySchedule::create([
            'organization_id' => $agreement->organization_id,
            'scheduling_agreement_id' => $agreement->id,
            'schedule_date' => now()->addWeek()->toDateString(),
            'scheduled_quantity' => $quantity,
            'received_quantity' => 0,
            'status' => SaDeliverySchedule::STATUS_OPEN,
        ]);
    }
}
