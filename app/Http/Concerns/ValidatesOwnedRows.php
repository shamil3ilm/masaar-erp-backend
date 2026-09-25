<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Models\Core\Organization;
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
 *
 * A field that names another company is the one case where a foreign
 * organization is the point, and inCallerGroup() bounds it to the caller's
 * parent-subsidiary group instead.
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
     * An exists rule for a field that names another company, such as the
     * receiver of an asset transfer or a consolidation group's entity: it
     * accepts only an organization of the caller's group, as
     * Organization::groupIds() defines a group.
     *
     * A caller whose group is empty - an organization id that names no
     * organization - matches nothing, so the rule refuses rather than opens.
     */
    protected function inCallerGroup(): Exists
    {
        return Rule::exists('organizations', 'id')
            ->whereIn('id', Organization::groupIds((int) auth()->user()->organization_id));
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
