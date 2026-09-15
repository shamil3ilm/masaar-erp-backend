<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\NumberSequence;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Configuration of an organization's document number sequences, one per
 * document type and optional branch. Numbers themselves are taken by
 * NumberSequence::getNext() under a row lock; a configuration change takes
 * the same lock so it cannot overwrite a number taken meanwhile.
 */
class NumberSequenceService
{
    /**
     * @return Collection<int, NumberSequence>
     */
    public function list(int $organizationId): Collection
    {
        return NumberSequence::where('organization_id', $organizationId)
            ->orderBy('type')
            ->get();
    }

    /**
     * The sequence for exactly this type and branch; a null branch means the
     * organization-wide sequence.
     */
    public function find(int $organizationId, string $type, ?int $branchId): ?NumberSequence
    {
        return NumberSequence::where('organization_id', $organizationId)
            ->where('type', $type)
            ->where('branch_id', $branchId)
            ->first();
    }

    /**
     * Creates or changes the sequence for a type and branch. A setting left
     * out takes its default; one sent as null keeps the stored value, or
     * the column default on a new sequence. The reset period restarts now.
     *
     * @param  array<string, mixed>  $values  prefix, suffix, padding, include_year, include_month, reset_yearly, reset_monthly, current_number
     */
    public function configure(int $organizationId, string $type, ?int $branchId, array $values): NumberSequence
    {
        $defaults = [
            'padding' => 5,
            'include_year' => true,
            'include_month' => false,
            'reset_yearly' => true,
            'reset_monthly' => false,
        ];

        $changes = [];

        foreach (['prefix', 'suffix', 'padding', 'include_year', 'include_month', 'reset_yearly', 'reset_monthly', 'current_number'] as $field) {
            $changes[$field] = array_key_exists($field, $values) ? $values[$field] : ($defaults[$field] ?? null);
        }

        $changes = array_filter(
            array_merge($changes, ['last_reset_year' => now()->year, 'last_reset_month' => now()->month]),
            fn ($value) => $value !== null
        );

        return DB::transaction(function () use ($organizationId, $type, $branchId, $changes) {
            $sequence = NumberSequence::where('organization_id', $organizationId)
                ->where('type', $type)
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                return NumberSequence::create(array_merge(
                    ['organization_id' => $organizationId, 'type' => $type, 'branch_id' => $branchId],
                    $changes
                ));
            }

            $sequence->update($changes);

            return $sequence;
        });
    }

    /**
     * The number this sequence will issue next, without taking it. The stored
     * current_number is the last one issued, so the next is one past it.
     */
    public function nextNumberOf(NumberSequence $sequence): string
    {
        $next = clone $sequence;
        $next->current_number++;

        return $next->getFormattedNumber();
    }

    /**
     * The number the next document of this type would get, without taking it.
     */
    public function peekNext(int $organizationId, string $type, ?int $branchId): string
    {
        return NumberSequence::peekNext($organizationId, $type, $branchId);
    }
}
