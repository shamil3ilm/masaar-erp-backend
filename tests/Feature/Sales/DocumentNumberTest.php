<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\NumberSequence;
use App\Models\Inventory\Product;
use App\Models\Inventory\UnitOfMeasure;
use App\Models\Sales\Contact;
use App\Models\Sales\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Quotations and sales orders are numbered from the sequence table.
 *
 * The number has to be unique and it has to advance. Both controllers used to
 * catch any failure from NumberSequence and fall back to counting existing
 * rows, which repeats a number as soon as one is deleted and hands the same
 * number to two callers at once. The fallback ran on every request, because
 * the columns NumberSequence needs had never been created.
 */
class DocumentNumberTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Contact $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'sales.quotations.view',
            'sales.quotations.create',
            'sales.orders.view',
            'sales.orders.create',
        ]);

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);

        $unit = UnitOfMeasure::factory()->create(['organization_id' => $this->organization->id]);

        $this->product = Product::factory()->create([
            'organization_id' => $this->organization->id,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_quotation_numbers_advance(): void
    {
        $first = $this->createQuotation();
        $second = $this->createQuotation();

        $this->assertNotSame($first, $second);
        $this->assertStringStartsWith('QUO-', $first);
        $this->assertStringStartsWith('QUO-', $second);
    }

    public function test_a_deleted_quotation_does_not_free_its_number(): void
    {
        $first = $this->createQuotation();

        $this->assertDatabaseHas('quotations', ['quotation_number' => $first]);
        Quotation::where('quotation_number', $first)->delete();

        $this->assertNotSame($first, $this->createQuotation());
    }

    public function test_the_sequence_row_advances(): void
    {
        $quotation = $this->createQuotation();

        $sequences = NumberSequence::where('organization_id', $this->organization->id)
            ->whereNotNull('type')
            ->pluck('current_number', 'type');

        $this->assertSame(1, (int) $sequences['quotation']);
        $this->assertStringStartsWith('QUO-', $quotation);
    }

    private function createQuotation(): string
    {
        $response = $this->apiPost('/sales/quotations', [
            'customer_id' => $this->customer->id,
            'quotation_date' => now()->format('Y-m-d'),
            'valid_until' => now()->addDays(14)->format('Y-m-d'),
            'currency_code' => 'SAR',
            'lines' => [
                [
                    'product_id' => $this->product->id,
                    'description' => 'Line',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'tax_rate' => 15.00,
                ],
            ],
        ]);

        $response->assertStatus(201);

        return $response->json('data.quotation_number');
    }
}
