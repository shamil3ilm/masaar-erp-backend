<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every enum a migration declares must actually constrain the column.
 *
 * enum() compiles to a CHECK constraint on SQLite and to a real ENUM on MySQL.
 * SQLite cannot alter a column in place, so Laravel rebuilds the table — and
 * the rebuild silently drops the CHECK. Any table altered after it was created
 * therefore lost its enum constraints in the test database while keeping them
 * in production.
 *
 * Twenty-two columns were in that state. The consequence is worse than a
 * missing constraint: the suite cannot fail on a value the schema forbids,
 * because in the environment the suite runs in it is not forbidden. work_orders
 * had a migration writing 'released' into a column declared without it, and a
 * docblock explaining that no ALTER was needed since the column "is a VARCHAR"
 * — true only because the constraint had already been lost. On MySQL that
 * statement fails.
 *
 * So this compares what the migrations declare against what the built database
 * enforces, and fails when they disagree. It does not check that the values are
 * the right ones; it checks that the tests are running against the schema the
 * migrations describe.
 */
class EnumConstraintTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATIONS = __DIR__.'/../../../database/migrations';

    /**
     * Columns whose CHECK is lost for a reason that cannot be designed away.
     *
     * leads and opportunities point at each other: a lead records the
     * opportunity it became, and the opportunity records the lead it came from.
     * One of those keys must be added after both tables exist, and on SQLite
     * adding a key rebuilds the table, taking the CHECK with it. MySQL keeps
     * both, so these columns are still constrained where it counts.
     *
     * A ratchet, not an allowance. The assertion is equality: a new unenforced
     * column fails the build, and one that gets fixed fails until removed here.
     */
    private const ACCEPTED = [
        'leads.lead_type (individual, company)',
        'leads.rating (hot, warm, cold)',
        'leads.status (new, contacted, qualified, unqualified, converted, lost)',
    ];

    public function test_declared_enums_are_enforced(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            // MySQL enforces enum() itself; this guard exists because SQLite does
            // not, and it reads the constraints out of sqlite_master to prove it.
            $this->markTestSkipped('Reads CHECK constraints out of sqlite_master.');
        }

        $declared = $this->declaredEnums();
        $this->assertNotEmpty($declared, 'No enum() declarations found — the parser is broken.');

        $enforced = $this->enforcedChecks();

        $unenforced = [];
        foreach ($declared as $key => $values) {
            [$table] = explode('.', $key);
            if (! isset($enforced[$table])) {
                continue;               // table not in this schema
            }
            if (! isset($enforced[$key])) {
                $unenforced[] = $key.' ('.implode(', ', $values).')';
            }
        }

        sort($unenforced);

        $this->assertSame(self::ACCEPTED, $unenforced, sprintf(
            'The set of columns declared enum() but carrying no CHECK constraint '
            .'has changed. A new one means the tests can no longer fail on a value '
            ."MySQL would reject, usually because a Schema::table() rebuilt the table.\n%s",
            implode("\n", $unenforced)
        ));
    }

    /** @return array<string, list<string>> "table.column" => values */
    private function declaredEnums(): array
    {
        $out = [];

        foreach (glob(self::MIGRATIONS.'/*.php') ?: [] as $file) {
            $src = (string) file_get_contents($file);

            foreach ($this->createBlocks($src) as $table => $body) {
                preg_match_all(
                    '/\$table->enum\(\s*\'([a-z_0-9]+)\'\s*,\s*\[(.*?)\]/s',
                    $body, $matches, PREG_SET_ORDER
                );

                foreach ($matches as $m) {
                    preg_match_all("/'([^']*)'/", $m[2], $values);
                    $out[$table.'.'.$m[1]] = $values[1];
                }
            }
        }

        return $out;
    }

    /** @return array<string, true> keyed by "table" and "table.column" */
    private function enforcedChecks(): array
    {
        $out = [];

        $rows = DB::select(
            "SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        );

        foreach ($rows as $row) {
            $out[$row->name] = true;
            $sql = (string) ($row->sql ?? '');

            preg_match_all('/check \("(\w+)" in \(/i', $sql, $m);
            foreach ($m[1] as $column) {
                $out[$row->name.'.'.$column] = true;
            }
        }

        return $out;
    }

    /**
     * Schema::create bodies, keyed by table.
     *
     * Brace matching rather than a regex: these bodies contain closures and
     * strings, and a lazy match stops at the first "});" inside one.
     */
    private function createBlocks(string $src): array
    {
        $out = [];
        $offset = 0;

        while (preg_match("/Schema::create\(\s*'([a-z_0-9]+)'/", $src, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $table = $m[1][0];
            $start = strpos($src, '{', $m[0][1]);

            if ($start === false) {
                break;
            }

            $depth = 0;
            $i = $start;
            $length = strlen($src);

            while ($i < $length) {
                if ($src[$i] === '{') {
                    $depth++;
                } elseif ($src[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
                $i++;
            }

            $out[$table] = substr($src, $start, $i - $start);
            $offset = $i;
        }

        return $out;
    }
}
