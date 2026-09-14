<?php

declare(strict_types=1);

namespace Tests\Feature\Tax;

use App\Models\Tax\VatReturnPeriod;
use App\Models\Tax\VatTransaction;
use App\Services\Tax\VatReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\TestHelpers;

/**
 * A VAT return's boxes are built and the return submitted on the locked
 * period, and a submitted return is never rebuilt or submitted again.
 */
class VatReturnTransitionTest extends TestCase
{
    use AssertsRejection, RefreshDatabase, TestHelpers;

    private VatReturnService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');

        $this->service = app(VatReturnService::class);
    }

    public function test_a_stale_return_is_not_rebuilt_once_it_has_been_submitted(): void
    {
        $this->sale(1000, 150);
        $period = $this->service->buildReturnBoxes($this->period());
        $stale = VatReturnPeriod::findOrFail($period->id);

        $this->service->submitReturn($period, 'ZATCA-001');
        $this->sale(500, 75);

        $this->assertRejected(fn () => $this->service->buildReturnBoxes($stale));

        $submitted = $period->fresh('boxes');
        $this->assertSame('submitted', $submitted->status);
        $this->assertEquals(1000, (float) $submitted->boxes->firstWhere('box_number', '1')->output_amount);
    }

    public function test_a_second_submission_from_a_stale_return_is_rejected(): void
    {
        $period = $this->service->buildReturnBoxes($this->period());
        $stale = VatReturnPeriod::findOrFail($period->id);

        $this->service->submitReturn($period, 'ZATCA-001');

        $this->assertRejected(fn () => $this->service->submitReturn($stale, 'ZATCA-002'));
        $this->assertSame('ZATCA-001', $period->fresh()->reference_number);
    }

    public function test_preparing_the_same_period_twice_returns_that_period(): void
    {
        $first = $this->period();
        $second = $this->period();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, VatReturnPeriod::count());
    }

    private function period(): VatReturnPeriod
    {
        return $this->service->preparePeriod($this->organization, 'SAU', '2026-07-01', '2026-09-30');
    }

    private function sale(float $taxable, float $vat): void
    {
        $this->service->recordTransaction([
            'organization_id' => $this->organization->id,
            'transaction_type' => VatTransaction::TYPE_SALE,
            'tax_period' => '2026-08-15',
            'taxable_amount' => $taxable,
            'vat_amount' => $vat,
            'vat_rate' => 15,
            'country_code' => 'SAU',
        ]);
    }
}
