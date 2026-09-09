<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * An exists or unique rule has to name a real table and column.
 *
 * The rule runs a query. Against a table that is not there it does not reject
 * the input, it raises, so the endpoint answers 500 to any request carrying
 * that field — and only to requests carrying it, which is why an empty body
 * never found these.
 *
 * Eighteen named tables that have never existed: hr_employees, sales_contacts,
 * inventory_products and the like, module prefixes on tables that are not
 * prefixed, plus payment_mades and payment_receiveds for payments_made and
 * payments_received.
 */
class ValidationRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_rule_names_a_real_column(): void
    {
        $bad = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $src = (string) file_get_contents($file->getPathname());
            $where = $file->getBasename();

            preg_match_all('/(exists|unique):([a-z0-9_]+)(?:,([a-z0-9_]+))?/', $src, $inline, PREG_SET_ORDER);

            preg_match_all(
                '/Rule::(exists|unique)\(\s*[\'"]([a-z0-9_]+)[\'"]\s*(?:,\s*[\'"]([a-z0-9_]+)[\'"])?/',
                $src,
                $fluent,
                PREG_SET_ORDER
            );

            foreach (array_merge($inline, $fluent) as $rule) {
                $table = $rule[2];
                $column = $rule[3] ?? 'id';

                if (! Schema::hasTable($table)) {
                    $bad[] = $where.': no table '.$table;

                    continue;
                }

                if (! Schema::hasColumn($table, $column)) {
                    $bad[] = $where.': '.$table.' has no '.$column;
                }
            }
        }

        $bad = array_values(array_unique($bad));
        sort($bad);

        $this->assertSame([], $bad, sprintf(
            "These validation rules query something that does not exist.\n%s",
            implode("\n", $bad)
        ));
    }
}
