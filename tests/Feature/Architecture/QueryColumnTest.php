<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A query has to name columns the table has.
 *
 * The ratchets so far check what a model declares. This checks what a query
 * asks for, which is where the same mistake does the most damage: SQLite reads
 * an unknown quoted identifier as a string, so a where clause on a column that
 * does not exist matches nothing and reports success. Two scheduled commands
 * had been doing exactly that — cleaning up no sessions and running no reports
 * — and only failed once CI ran them against MySQL.
 *
 * This finds the same thing without needing the code to run, which matters for
 * anything nothing calls.
 *
 * Deliberately narrow, because a wrong answer here is worse than no answer:
 *   - chains containing a join, union, whereHas or whereRelation are skipped,
 *     since a bare column may belong to the other table
 *   - qualified names (table.column) are skipped for the same reason
 *   - only literal column names are considered
 */
class QueryColumnTest extends TestCase
{
    use RefreshDatabase;

    private const BASELINE = __DIR__.'/../../Fixtures/query-columns.txt';

    /**
     * Methods whose first argument must be a real column.
     *
     * orderBy, groupBy and pluck are deliberately absent: SQL lets those name
     * a select alias, so `selectRaw('COUNT(*) as cnt')->orderBy('cnt')` is
     * correct and reads like a missing column. A WHERE cannot reference an
     * alias, which is what makes this list safe to judge.
     */
    private const COLUMN_METHODS = 'where|orWhere|whereNull|whereNotNull|whereIn|whereNotIn|'
        .'whereBetween|whereDate|whereYear|whereMonth|increment|decrement';

    /** A chain touching another table cannot be judged on bare column names. */
    private const AMBIGUOUS = '/->\s*(join|leftJoin|rightJoin|crossJoin|union|whereHas|orWhereHas|whereRelation|has)\s*\(/i';

    public function test_every_query_names_a_real_column(): void
    {
        $found = [];
        $checked = 0;

        foreach ($this->files() as $path) {
            $src = str_replace("\r\n", "\n", (string) file_get_contents($path));
            $tables = $this->tablesInScope($src);

            foreach ($this->chains($src) as [$offset, $table, $chain]) {
                if (! Schema::hasTable($table) || preg_match(self::AMBIGUOUS, $chain) === 1) {
                    continue;
                }

                $columns = Schema::getColumnListing($table);
                $checked++;

                preg_match_all(
                    '/->\s*(?:'.self::COLUMN_METHODS.')\s*\(\s*[\'"]([a-z0-9_.]+)[\'"]/i',
                    $chain,
                    $used,
                    PREG_SET_ORDER
                );

                foreach ($used as $hit) {
                    $column = $hit[1];

                    if (str_contains($column, '.') || in_array($column, $columns, true)) {
                        continue;
                    }

                    $line = substr_count(substr($src, 0, $offset), "\n") + 1;
                    $found[] = $this->relative($path).':'.$line.'  '.$table.'.'.$column;
                }
            }

            unset($tables);
        }

        $found = array_values(array_unique($found));
        sort($found);

        // A pass that examined nothing is not a pass.
        $this->assertGreaterThan(
            200,
            $checked,
            'Far fewer query chains than expected — the parser is probably broken.'
        );

        $declared = $this->baseline();

        $new = array_values(array_diff($found, $declared));
        sort($new);

        $this->assertSame([], $new, sprintf(
            "These queries name a column the table does not have.\n%s",
            implode("\n", $new)
        ));

        $fixed = array_values(array_diff($declared, $found));
        sort($fixed);

        $this->assertSame([], $fixed, sprintf(
            "These are in %s and no longer match. Remove them.\n%s",
            basename(self::BASELINE),
            implode("\n", $fixed)
        ));
    }

    /**
     * DB::table('x') and Model::where(...) chains, with the table each reads.
     *
     * @return list<array{0: int, 1: string, 2: string}>
     */
    private function chains(string $src): array
    {
        $out = [];
        $imports = $this->tablesInScope($src);

        preg_match_all(
            '/DB::table\(\s*[\'"]([a-z0-9_]+)[\'"]\s*\)/i',
            $src,
            $raw,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        foreach ($raw as $hit) {
            $out[] = [$hit[0][1], $hit[1][0], $this->chainAt($src, $hit[0][1])];
        }

        // Model::where(...) where the short name is imported and is a model.
        foreach ($imports as $short => $table) {
            preg_match_all(
                '/\b'.preg_quote($short, '/').'::\s*(?:'.self::COLUMN_METHODS.')\s*\(/',
                $src,
                $eloquent,
                PREG_OFFSET_CAPTURE | PREG_SET_ORDER
            );

            foreach ($eloquent as $hit) {
                $out[] = [$hit[0][1], $table, $this->chainAt($src, $hit[0][1])];
            }
        }

        return $out;
    }

    private function chainAt(string $src, int $offset): string
    {
        $chain = substr($src, $offset, 1500);
        $end = strpos($chain, ';');

        return $end === false ? $chain : substr($chain, 0, $end);
    }

    /**
     * Imported model classes in a file, as short name => table.
     *
     * @return array<string, string>
     */
    private function tablesInScope(string $src): array
    {
        $out = [];
        $sep = chr(92);

        preg_match_all('/^use (App'.$sep.$sep.'Models'.$sep.$sep.'[^;]+);/m', $src, $m);

        foreach ($m[1] as $class) {
            if (! class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $out[substr($class, strrpos($class, $sep) + 1)] = (new $class)->getTable();
        }

        return $out;
    }

    /** @return list<string> */
    private function files(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($file->getExtension() === 'php') {
                $out[] = $file->getPathname();
            }
        }

        sort($out);

        return $out;
    }

    private function relative(string $path): string
    {
        return str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $path);
    }

    /** @return list<string> */
    private function baseline(): array
    {
        $out = [];

        foreach (file(self::BASELINE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (! str_starts_with($line, '#')) {
                $out[] = $line;
            }
        }

        sort($out);

        return $out;
    }
}
