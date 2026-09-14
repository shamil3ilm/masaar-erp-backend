<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\Bill;
use App\Models\Purchase\ThreeWayMatchResult;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Three-way match result listings: the organization's results only, and the
 * bill's supplier tax number leaves masked.
 */
class ThreeWayMatchEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const TAX_NUMBER = '300123456700003';

    private string $baseUrl = '/purchase/three-way-match';
    private Bill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['purchase.bills.view']);

        $this->bill = Bill::factory()->approved()->create([
            'organization_id' => $this->organization->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $this->organization->id])->id,
            'supplier_tax_number' => self::TAX_NUMBER,
        ]);

        $this->matchResult($this->bill, 'matched');
        $this->matchResult($this->bill, 'price_variance');

        $other = Organization::factory()->create();
        $this->matchResult(Bill::factory()->approved()->create([
            'organization_id' => $other->id,
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
        ]), 'price_variance');
    }

    public function test_index_lists_the_organizations_results_with_the_bill_masked(): void
    {
        $response = $this->apiGet($this->baseUrl);

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.bill.id', $this->bill->id)
            ->assertJsonPath('data.0.bill.supplier_tax_number', '***********0003');
        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());

        $this->apiGet("{$this->baseUrl}?match_status=matched")->assertOk()->assertJsonPath('meta.total', 1);
        $this->apiGet("{$this->baseUrl}?bill_id={$this->bill->id}")->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_exceptions_list_only_unmatched_results_with_the_bill_masked(): void
    {
        $response = $this->apiGet("{$this->baseUrl}/exceptions");

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.match_status', 'price_variance')
            ->assertJsonPath('data.0.bill.supplier_tax_number', '***********0003');
        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    private function matchResult(Bill $bill, string $status): ThreeWayMatchResult
    {
        return ThreeWayMatchResult::create([
            'organization_id' => $bill->organization_id,
            'bill_id' => $bill->id,
            'match_status' => $status,
            'quantity_match' => $status === 'matched',
            'price_match' => $status === 'matched',
        ]);
    }
}
