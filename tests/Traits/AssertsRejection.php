<?php

declare(strict_types=1);

namespace Tests\Traits;

trait AssertsRejection
{
    /**
     * Asserts that $action is refused with the exceptions services use for a
     * transition that is not allowed in the record's current state.
     */
    protected function assertRejected(callable $action): void
    {
        try {
            $action();
        } catch (\InvalidArgumentException|\LogicException) {
            return;
        }

        $this->fail('The operation was expected to be rejected.');
    }
}
