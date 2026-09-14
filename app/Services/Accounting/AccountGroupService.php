<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\AccountGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AccountGroupService
{
    /**
     * Account groups of the current organization, ordered by code.
     *
     * @param  array{active_only?: bool, account_category?: mixed}  $filters
     *         account_category applies only when truthy.
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return AccountGroup::query()
            ->when($filters['active_only'] ?? false, fn ($q) => $q->active())
            ->when($filters['account_category'] ?? null, fn ($q, $category) => $q->where('account_category', $category))
            ->orderBy('code')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data  Validated account group attributes.
     */
    public function create(array $data, int $organizationId): AccountGroup
    {
        return AccountGroup::create([...$data, 'organization_id' => $organizationId]);
    }
}
