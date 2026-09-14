<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Exists rules for ids of tenant-owned rows.
 *
 * An exists rule on the table alone accepts another organization's id: the request then
 * either links that row into this organization's document or learns that the
 * id exists. These rules accept only a row of the caller's organization.
 */
trait ValidatesOwnedRows
{
    /**
     * An exists rule that accepts only a row of the caller's organization.
     */
    protected function ownedBy(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('organization_id', auth()->user()->organization_id);
    }
}
