<?php

declare(strict_types=1);

namespace Tests\Feature\RealEstate;

use App\Models\RealEstate\RentalContract;
use App\Models\RealEstate\SecurityDeposit;
use App\Services\RealEstate\SecurityDepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\TestHelpers;

/**
 * Collections and refunds change a deposit's amounts from the locked row, and
 * no refund takes back more than was collected and not yet refunded.
 */
class SecurityDepositTransitionTest extends TestCase
{
    use AssertsRejection, RefreshDatabase, TestHelpers;

    private SecurityDepositService $service;
    private SecurityDeposit $deposit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('AE');
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');

        $this->service = app(SecurityDepositService::class);
        $this->deposit = $this->service->createSecurityDeposit($this->rentalContract(), [
            'required_amount' => 1000,
            'currency_code' => 'AED',
        ]);
    }

    public function test_a_collection_adds_to_the_amount_on_the_locked_deposit(): void
    {
        $stale = SecurityDeposit::findOrFail($this->deposit->id);

        $this->service->recordDepositCollection($this->deposit, 600, now()->toDateString());
        $this->service->recordDepositCollection($stale, 400, now()->toDateString());

        $deposit = $this->deposit->fresh();
        $this->assertEquals(1000, (float) $deposit->collected_amount);
        $this->assertSame('collected', $deposit->status);
    }

    public function test_a_refund_cannot_exceed_what_is_left_after_earlier_refunds(): void
    {
        $this->service->recordDepositCollection($this->deposit, 1000, now()->toDateString());
        $this->service->refundDeposit($this->deposit->fresh(), 700, 'Move-out, damages deducted');

        $this->assertRejected(fn () => $this->service->refundDeposit($this->deposit->fresh(), 700, 'Refunded again'));

        $this->assertEquals(700, (float) $this->deposit->fresh()->refunded_amount);
    }

    public function test_a_refund_from_a_stale_deposit_counts_the_refunds_made_since(): void
    {
        $this->service->recordDepositCollection($this->deposit, 1000, now()->toDateString());
        $stale = SecurityDeposit::findOrFail($this->deposit->id);

        $this->service->refundDeposit($this->deposit->fresh(), 600, 'Move-out');

        $this->assertRejected(fn () => $this->service->refundDeposit($stale, 600, 'Move-out'));
        $this->assertEquals(600, (float) $this->deposit->fresh()->refunded_amount);
    }

    private function rentalContract(): RentalContract
    {
        $organizationId = $this->organization->id;
        $row = fn (array $columns): array => array_merge([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $columns);

        $portfolio = DB::table('portfolios')->insertGetId($row(['code' => 'PF-1', 'name' => 'Gulf portfolio']));
        $property = DB::table('properties')->insertGetId($row(['portfolio_id' => $portfolio, 'code' => 'PR-1', 'name' => 'Business tower']));
        $building = DB::table('buildings')->insertGetId($row(['property_id' => $property, 'code' => 'B-1', 'name' => 'Tower A']));
        $unit = DB::table('rental_units')->insertGetId($row(['building_id' => $building, 'code' => 'U-501']));
        $contract = DB::table('rental_contracts')->insertGetId($row([
            'contract_number' => 'RC-1',
            'rental_unit_id' => $unit,
            'start_date' => now()->toDateString(),
        ]));

        return RentalContract::findOrFail($contract);
    }
}
