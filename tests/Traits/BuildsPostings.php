<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Support\Decimal;

/**
 * Journal entries and their lines, written straight to the tables so a report
 * test states the postings it measures rather than driving the whole posting
 * workflow to reach them.
 */
trait BuildsPostings
{
    /**
     * An account of $organizationId, outside the chart TestHelpers builds.
     */
    protected function accountFor(
        int $organizationId,
        string $code,
        string $type,
        string $subType,
        array $attributes = []
    ): Account {
        return Account::factory()->create([
            'organization_id' => $organizationId,
            'code' => $code,
            'name' => "Account {$code}",
            'account_type' => $type,
            'sub_type' => $subType,
            'currency_code' => null,
            ...$attributes,
        ]);
    }

    /**
     * One journal entry of $organizationId dated $date, with a line per
     * [account, debit, credit] triple.
     *
     * @param  list<array{0: Account, 1: string, 2: string}>  $lines
     */
    protected function postEntry(
        int $organizationId,
        string $date,
        array $lines,
        array $attributes = []
    ): JournalEntry {
        $entry = JournalEntry::factory()->create([
            'organization_id' => $organizationId,
            'entry_date' => $date,
            'status' => JournalEntry::STATUS_POSTED,
            'currency_code' => 'SAR',
            'exchange_rate' => '1.00000000',
            'source_type' => null,
            ...$attributes,
        ]);

        foreach ($lines as $index => [$account, $debit, $credit]) {
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
                // Left null so the model derives them from the entry's rate.
                'base_debit' => null,
                'base_credit' => null,
                'line_order' => $index + 1,
            ]);
        }

        return $entry->refresh();
    }

    /**
     * $value as a decimal string, so an assertion compares figures rather than
     * floats.
     */
    protected function money(mixed $value, int $scale = 4): string
    {
        return Decimal::at(is_bool($value) || $value === null ? null : $value, $scale);
    }

    /**
     * The report line for $code, or null when the report left the account out.
     */
    protected function lineFor(array $lines, string $code): ?array
    {
        foreach ($lines as $line) {
            if (($line['account_code'] ?? null) === $code) {
                return $line;
            }
        }

        return null;
    }
}
