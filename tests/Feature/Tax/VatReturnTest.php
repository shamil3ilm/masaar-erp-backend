<?php

declare(strict_types=1);

namespace Tests\Feature\Tax;

use App\Models\Tax\VatReturnPeriod;
use App\Models\Tax\VatTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A VAT return nets credit notes, refunds and returns against the sales they
 * reverse, in its boxes and its reconciliation summary alike.
 */
class VatReturnTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['tax.vat.view', 'tax.vat.manage']);
    }

    public function test_a_credit_note_reduces_output_vat_in_the_boxes_and_the_summary(): void
    {
        $this->record(VatTransaction::TYPE_SALE, 1000, 150);
        $this->record(VatTransaction::TYPE_CREDIT_NOTE, -200, -30);
        $this->record(VatTransaction::TYPE_PURCHASE, 400, 60);

        $this->apiPost('/vat-returns', [
            'country_code' => 'SAU',
            'period_start' => '2026-07-01',
            'period_end' => '2026-09-30',
        ])->assertStatus(201);

        $period = VatReturnPeriod::sole();

        $boxes = collect(
            $this->apiPost("/vat-returns/{$period->uuid}/build-boxes")->assertOk()->json('data.boxes')
        )->keyBy('box_number');

        $this->assertEquals(800, $boxes['1']['output_amount']);
        $this->assertEquals(120, $boxes['4']['output_amount']);
        $this->assertEquals(60, $boxes['6']['input_amount']);
        $this->assertEquals(60, $boxes['7']['net_vat']);

        $summary = $this->apiGet("/vat-returns/{$period->uuid}/export")->assertOk()->json('data.reconciliation');

        $this->assertEquals(800, $summary['output_taxable_amount']);
        $this->assertEquals(120, $summary['output_vat']);
        $this->assertEquals(60, $summary['net_vat_payable']);
    }

    public function test_amounts_carry_the_sign_of_their_type_and_adjustments_are_refused(): void
    {
        $this->apiPost('/vat-returns/transactions', $this->transaction(VatTransaction::TYPE_CREDIT_NOTE, 200, 30))
            ->assertStatus(422);

        $this->apiPost('/vat-returns/transactions', $this->transaction(VatTransaction::TYPE_SALE, -100, -15))
            ->assertStatus(422);

        $this->apiPost('/vat-returns/transactions', $this->transaction('adjustment', 100, 15))
            ->assertStatus(422);

        $this->assertSame(0, VatTransaction::count());
    }

    private function record(string $type, float $taxable, float $vat): void
    {
        $this->apiPost('/vat-returns/transactions', $this->transaction($type, $taxable, $vat))->assertStatus(201);
    }

    private function transaction(string $type, float $taxable, float $vat): array
    {
        return [
            'transaction_type' => $type,
            'tax_period' => '2026-08-15',
            'taxable_amount' => $taxable,
            'vat_amount' => $vat,
            'vat_rate' => 15,
            'country_code' => 'SAU',
        ];
    }
}
