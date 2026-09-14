<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\HR\Employee;
use App\Models\HR\EosbPolicy;
use App\Models\HR\EosbSettlement;
use App\Services\HR\EosbService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AssertsRejection;
use Tests\Traits\BuildsLedger;
use Tests\Traits\TestHelpers;

/**
 * Paying an end-of-service settlement runs once, on the locked settlement,
 * and only together with its journal entry.
 */
class EosbSettlementTransitionTest extends TestCase
{
    use AssertsRejection, BuildsLedger, RefreshDatabase, TestHelpers;

    private EosbService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser();
        $this->setUpOpenFiscalPeriod();
        $this->actingAs($this->user, 'api');

        $this->ledgerAccount('2200', 'EOSB Provision', Account::TYPE_LIABILITY, Account::SUBTYPE_OTHER_LIABILITY);
        $this->ledgerAccount('1010', 'Main Bank', Account::TYPE_ASSET, Account::SUBTYPE_BANK);

        $this->service = app(EosbService::class);
    }

    public function test_a_second_payment_from_a_stale_settlement_is_rejected(): void
    {
        $settlement = $this->approvedSettlement(5000);
        $stale = EosbSettlement::findOrFail($settlement->id);

        $this->service->markSettlementPaid($settlement, now()->toDateString());

        $this->assertRejected(fn () => $this->service->markSettlementPaid($stale, now()->toDateString()));
        $this->assertSame(1, JournalEntry::where('source_type', EosbSettlement::class)->count());
    }

    public function test_marking_paid_rolls_back_when_the_journal_entry_cannot_be_posted(): void
    {
        $settlement = $this->approvedSettlement(5000);
        $this->closeFiscalYear();

        $this->assertRejected(fn () => $this->service->markSettlementPaid($settlement, now()->toDateString()));

        $this->assertSame(EosbSettlement::STATUS_APPROVED, $settlement->fresh()->status);
        $this->assertSame(0, JournalEntry::count());
    }

    private function approvedSettlement(float $netAmount): EosbSettlement
    {
        $employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
        ]);

        $policy = EosbPolicy::forceCreate([
            'organization_id' => $this->organization->id,
            'country_code' => 'sa',
            'calculation_method' => 'saudi',
        ]);

        return EosbSettlement::create([
            'organization_id' => $this->organization->id,
            'employee_id' => $employee->id,
            'eosb_policy_id' => $policy->id,
            'termination_date' => now()->toDateString(),
            'years_of_service' => 5,
            'total_days_earned' => 75,
            'daily_rate' => $netAmount / 75,
            'gross_amount' => $netAmount,
            'net_amount' => $netAmount,
            'status' => EosbSettlement::STATUS_APPROVED,
        ]);
    }
}
