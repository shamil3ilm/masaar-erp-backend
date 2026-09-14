<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use LogicException;

/**
 * Runs a change to one row against the current, locked copy of that row.
 *
 * The instance a caller holds can be stale: two requests may both load a
 * pending payment before either completes it, and a guard checked on those
 * instances passes twice. lockForTransition() opens a transaction, re-reads
 * the row with SELECT ... FOR UPDATE and passes that copy to the callback, so
 * the guard, the balance arithmetic and the journal posting see what is in
 * the database while other writers of the row wait. Any exception thrown by
 * the callback, a journal failure included, rolls the whole change back.
 */
trait LocksForTransition
{
    /**
     * @template TReturn
     *
     * @param  callable(static): TReturn  $callback
     * @return TReturn
     */
    public function lockForTransition(callable $callback): mixed
    {
        return $this->getConnection()->transaction(
            fn () => $callback($this->lockedCopy())
        );
    }

    /**
     * This row, re-read and locked until the surrounding transaction ends.
     */
    public function lockedCopy(): static
    {
        if ($this->getConnection()->transactionLevel() === 0) {
            throw new LogicException(
                'lockedCopy() must run inside a transaction; outside one the lock ends with the statement.'
            );
        }

        return static::query()->lockForUpdate()->findOrFail($this->getKey());
    }
}
