<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Accounting\JournalEntry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds journal entries for listing, newest entry date first.
 *
 * Filters are passed as the keys the caller received, so a filter that is
 * present with an empty value still applies, as it does on the API.
 */
class JournalEntryQueryService
{
    /**
     * @param  array{status?: mixed, fiscal_year_id?: mixed, start_date?: mixed, end_date?: mixed, search?: mixed}  $filters
     * @return Builder<JournalEntry>
     */
    public function query(array $filters): Builder
    {
        return JournalEntry::with(['branch:id,name', 'createdBy:id,name'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->when(array_key_exists('status', $filters), fn ($q) => $q->where('status', $filters['status']))
            ->when(array_key_exists('fiscal_year_id', $filters), fn ($q) => $q->where('fiscal_year_id', $filters['fiscal_year_id']))
            ->when(array_key_exists('start_date', $filters), fn ($q) => $q->whereDate('entry_date', '>=', $filters['start_date']))
            ->when(array_key_exists('end_date', $filters), fn ($q) => $q->whereDate('entry_date', '<=', $filters['end_date']))
            ->when(array_key_exists('search', $filters), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($q) use ($search) {
                    $q->where('entry_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });
    }
}
