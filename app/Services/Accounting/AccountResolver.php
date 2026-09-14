<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\System\Setting;

/**
 * Finds an organization's account for a posting role.
 *
 * Services picked accounts by matching names — '%Accrued%', '%Advance%' — which
 * finds "Accrued Salaries" for goods received and "Employee Advances" for a
 * vendor prepayment, and changes where postings land when an account is
 * renamed. An account has a sub-type for the roles the chart of accounts
 * names (inventory, payable, bank, cash); the rest are mapped explicitly per
 * organization in settings, and a role with neither resolves to nothing.
 */
class AccountResolver
{
    /**
     * The organization's postable account of a sub-type, preferring the system
     * account the chart of accounts seeds.
     */
    public function bySubType(int $organizationId, string $subType): ?Account
    {
        return Account::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('sub_type', $subType)
            ->where('is_header', false)
            ->where('is_active', true)
            ->orderByDesc('is_system')
            ->orderBy('code')
            ->first();
    }

    /**
     * The account a payment is made from or into when the document names none:
     * the organization's bank account, or its cash account when it has no bank
     * account.
     */
    public function bankOrCash(int $organizationId): ?Account
    {
        return $this->bySubType($organizationId, Account::SUBTYPE_BANK)
            ?? $this->bySubType($organizationId, Account::SUBTYPE_CASH);
    }

    /**
     * The account an organization mapped to a role in its accounting settings,
     * e.g. 'grni_account_id'. Only an account of that organization qualifies.
     */
    public function mapped(int $organizationId, string $key): ?Account
    {
        $id = Setting::get('accounting', $key, null, $organizationId);

        if ($id === null || $id === '') {
            return null;
        }

        return Account::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereKey((int) $id)
            ->first();
    }
}
