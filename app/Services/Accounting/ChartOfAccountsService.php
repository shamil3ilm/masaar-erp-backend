<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Accounting\Account;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Maintains an organization's chart of accounts: listing, adding, changing and
 * removing accounts, and seeding the default chart.
 *
 * Filters are passed as the keys the caller received, so a filter that is
 * present with an empty value still applies, as it does on the API.
 */
class ChartOfAccountsService
{
    public function __construct(
        private readonly ChartOfAccountsSeeder $defaultChart,
    ) {}

    /**
     * Top-level accounts with their children, ordered by code.
     *
     * @param  array{type?: string|null, active_only?: bool}  $filters
     * @return Collection<int, Account>
     */
    public function tree(array $filters): Collection
    {
        return Account::with('children')
            ->whereNull('parent_id')
            ->orderBy('code')
            ->when(array_key_exists('type', $filters), fn ($q) => $q->where('account_type', $filters['type']))
            ->when($filters['active_only'] ?? false, fn ($q) => $q->where('is_active', true))
            ->get();
    }

    /**
     * All accounts ordered by code, left as a query so the caller can page it.
     *
     * @param  array{type?: string|null, postable?: bool, active_only?: bool}  $filters
     * @return Builder<Account>
     */
    public function flatQuery(array $filters): Builder
    {
        return Account::query()
            ->orderBy('code')
            ->when(array_key_exists('type', $filters), fn ($q) => $q->where('account_type', $filters['type']))
            ->when($filters['postable'] ?? false, fn ($q) => $q->where('is_header', false)->where('is_active', true))
            ->when($filters['active_only'] ?? false, fn ($q) => $q->where('is_active', true));
    }

    /**
     * Add an account under its parent, deriving its level and dotted code path.
     *
     * @param  array<string, mixed>  $data  validated account attributes
     */
    public function create(int $organizationId, array $data): Account
    {
        $level = 1;
        $path = $data['code'];

        if (! empty($data['parent_id'])) {
            $parent = Account::findOrFail($data['parent_id']);
            $level = $parent->level + 1;
            $path = "{$parent->path}.{$data['code']}";
        }

        return Account::create([
            ...$data,
            'organization_id' => $organizationId,
            'level' => $level,
            'path' => $path,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws BusinessRuleException when the account is a system account
     */
    public function update(Account $account, array $data): Account
    {
        if ($account->is_system) {
            throw new BusinessRuleException('System accounts cannot be modified', 'SYSTEM_ACCOUNT', 403);
        }

        $account->update($data);

        return $account;
    }

    /**
     * Remove an account that is not a system account, has no children and
     * carries no journal lines.
     *
     * @throws BusinessRuleException when any of those holds
     */
    public function delete(Account $account): void
    {
        if ($account->is_system) {
            throw new BusinessRuleException('System accounts cannot be deleted', 'SYSTEM_ACCOUNT', 400);
        }

        if ($account->children()->exists()) {
            throw new BusinessRuleException('Cannot delete account with child accounts', 'HAS_CHILDREN', 400);
        }

        if ($account->journalLines()->exists()) {
            throw new BusinessRuleException('Cannot delete account with journal entries', 'HAS_TRANSACTIONS', 400);
        }

        $account->delete();
    }

    /**
     * Seed the default chart for an organization that has no accounts yet. The
     * chart is written in one transaction, so a failure leaves no partial chart
     * behind to block the next attempt.
     *
     * @throws BusinessRuleException when the organization already has accounts
     */
    public function initializeDefaults(int $organizationId): void
    {
        if (Account::where('organization_id', $organizationId)->exists()) {
            throw new BusinessRuleException('Chart of accounts already exists', 'ALREADY_EXISTS', 400);
        }

        DB::transaction(fn () => $this->defaultChart->createDefaultAccounts($organizationId));
    }
}
