<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;

/**
 * Chart-of-accounts rows and fiscal-year state for the organization set up by
 * TestHelpers.
 */
trait BuildsLedger
{
    protected function ledgerAccount(string $code, string $name, string $type, string $subType): Account
    {
        return Account::factory()->create([
            'organization_id' => $this->organization->id,
            'account_type' => $type,
            'sub_type' => $subType,
            'code' => $code,
            'name' => $name,
            'is_system' => true,
            'currency_code' => null,
        ]);
    }

    /** Closes the organization's fiscal years, so no journal entry can be posted. */
    protected function closeFiscalYear(): void
    {
        FiscalYear::withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)
            ->update(['is_closed' => true]);
    }
}
