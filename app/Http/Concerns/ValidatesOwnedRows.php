<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Exists rules for ids of tenant-owned rows.
 *
 * An exists rule on the table alone accepts another organization's id: the request then
 * either links that row into this organization's document or learns that the
 * id exists. These rules accept only a row of the caller's organization. Users
 * carry no tenant scope, so a user id is checked through ownedBy('users') like
 * any other table.
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

    /**
     * An exists rule for a table without an organization column, such as order
     * lines or product variants: the row is accepted only when the parent it
     * points to through $foreignKey belongs to the caller's organization.
     */
    protected function ownedThrough(string $table, string $foreignKey, string $parentTable): Exists
    {
        $organizationId = auth()->user()->organization_id;

        return Rule::exists($table, 'id')->where(
            fn (Builder $query) => $query->whereIn(
                $foreignKey,
                fn (Builder $parents) => $parents->select('id')->from($parentTable)->where('organization_id', $organizationId)
            )
        );
    }

    /**
     * A product variant whose product belongs to the caller's organization.
     */
    protected function ownedVariant(): Exists
    {
        return $this->ownedThrough('product_variants', 'product_id', 'products');
    }

    /**
     * A warehouse location whose warehouse belongs to the caller's organization.
     */
    protected function ownedLocation(): Exists
    {
        return $this->ownedThrough('warehouse_locations', 'warehouse_id', 'warehouses');
    }
}
