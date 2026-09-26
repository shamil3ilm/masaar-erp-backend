<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Rows of any table for a given organization, for tests that check a request
 * cannot reference another organization's data.
 *
 * An exists rule looks only at the id and the organization column, so a row
 * needs no more than the columns its table requires. Those are filled from the
 * schema: required foreign keys get a row of their own in the same
 * organization, enum columns their first allowed value, and every other column
 * a placeholder of its type.
 */
trait BuildsTenantRows
{
    private ?Organization $otherTenant = null;

    protected function otherTenant(): Organization
    {
        return $this->otherTenant ??= Organization::factory()->create();
    }

    /**
     * Insert a row of $table owned by $organizationId and return its id.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function tenantRow(string $table, int $organizationId, array $attributes = []): int
    {
        if ($table === 'users') {
            return User::factory()->create(['organization_id' => $organizationId] + $attributes)->id;
        }

        if ($table === 'organizations') {
            return $organizationId;
        }

        $row = $attributes;
        $foreignKeys = $this->requiredForeignKeys($table);
        $allowedValues = $this->enumValues($table);

        foreach (Schema::getColumns($table) as $column) {
            $name = $column['name'];

            if (array_key_exists($name, $row) || $column['nullable'] || $column['default'] !== null || $column['auto_increment']) {
                continue;
            }

            $row[$name] = match (true) {
                $name === 'organization_id' => $organizationId,
                isset($foreignKeys[$name]) => $this->referencedValue($foreignKeys[$name], $organizationId),
                isset($allowedValues[$name]) => $allowedValues[$name][0],
                default => $this->placeholder($name, strtolower($column['type_name'])),
            };
        }

        return (int) DB::table($table)->insertGetId($row);
    }

    /**
     * @return array<string, array{table: string, column: string}>
     */
    private function requiredForeignKeys(string $table): array
    {
        $keys = [];

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            $keys[$foreignKey['columns'][0]] = [
                'table' => $foreignKey['foreign_table'],
                'column' => $foreignKey['foreign_columns'][0],
            ];
        }

        return $keys;
    }

    /**
     * @param  array{table: string, column: string}  $reference
     */
    private function referencedValue(array $reference, int $organizationId): mixed
    {
        if ($reference['column'] !== 'id') {
            return DB::table($reference['table'])->value($reference['column']);
        }

        return $this->tenantRow($reference['table'], $organizationId);
    }

    /**
     * Allowed values of each enum column.
     *
     * The two drivers keep the list in different places: MySQL carries it in
     * the column's own type, enum('draft','posted'), while SQLite stores the
     * column as a varchar and keeps the list in a check constraint. Reading
     * only the SQLite one left every enum column unknown on MySQL, so it got a
     * placeholder string, which MySQL refuses outright - and every test built
     * on this trait failed there while passing here.
     *
     * @return array<string, list<string>>
     */
    private function enumValues(string $table): array
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? $this->enumValuesFromCheckConstraints($table)
            : $this->enumValuesFromColumnTypes($table);
    }

    /**
     * @return array<string, list<string>>
     */
    private function enumValuesFromCheckConstraints(string $table): array
    {
        $sql = (string) DB::table('sqlite_master')->where('type', 'table')->where('name', $table)->value('sql');
        preg_match_all('/check \("(\w+)" in \(([^)]*)\)\)/i', $sql, $matches, PREG_SET_ORDER);

        $values = [];
        foreach ($matches as [, $column, $list]) {
            preg_match_all("/'([^']*)'/", $list, $items);
            $values[$column] = $items[1];
        }

        return $values;
    }

    /**
     * @return array<string, list<string>>
     */
    private function enumValuesFromColumnTypes(string $table): array
    {
        $values = [];

        foreach (Schema::getColumns($table) as $column) {
            if (preg_match('/^(?:enum|set)\((.*)\)$/i', (string) ($column['type'] ?? ''), $match) !== 1) {
                continue;
            }

            preg_match_all("/'((?:[^']|'')*)'/", $match[1], $items);

            $values[$column['name']] = array_map(
                static fn (string $item): string => str_replace("''", "'", $item),
                $items[1],
            );
        }

        return $values;
    }

    private function placeholder(string $name, string $type): mixed
    {
        return match (true) {
            $name === 'uuid' => (string) Str::uuid(),
            str_contains($type, 'int') => random_int(1, 30000),
            in_array($type, ['numeric', 'decimal', 'float', 'double', 'real'], true) => 0,
            $type === 'date' => now()->toDateString(),
            in_array($type, ['datetime', 'timestamp'], true) => now()->toDateTimeString(),
            $type === 'time' => '09:00:00',
            in_array($type, ['json', 'jsonb'], true) => '{}',
            default => 'T-'.Str::lower(Str::random(10)),
        };
    }
}
