<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Architecture\Concerns\ScansSource;
use Tests\TestCase;

/**
 * Keeps request validation from accepting another organization's row id.
 *
 * 'account_id' => 'exists:chart_of_accounts,id' passes for any row in the
 * table, so the request either links another organization's row into this
 * organization's document or learns that the id exists. The helpers in
 * App\Http\Concerns\ValidatesOwnedRows narrow the check to rows the caller's
 * organization owns.
 *
 * The scan reads every exists: string rule and every Rule::exists() call under
 * app/Http. A reference is scoped when its own expression - the call with the
 * methods chained onto it, however many lines that spans - names
 * organization_id in a where(), directly or inside a closure, and the helpers
 * in the ValidatesOwnedRows traits are scoped by definition. An unscoped
 * reference fails when its table carries an organization_id column, and the
 * organizations table counts as well: it carries no such column, but a field
 * that names another company must stay inside the caller's parent-subsidiary
 * group, which inCallerGroup() limits it to. The tables left over are global,
 * such as currencies, or are reached through a parent that is scoped instead.
 *
 * UNSCOPED is exact, carrying how many references each file holds. A new
 * unscoped reference on a tenant-owned table fails until it is scoped or
 * listed with a reason, and a listed entry that has been scoped or removed
 * fails until it is delisted.
 */
class OwnedReferenceRuleTest extends TestCase
{
    use RefreshDatabase;
    use ScansSource;

    /** The column that marks a table as belonging to one organization. */
    private const TENANT_COLUMN = 'organization_id';

    /**
     * The table of the tenants themselves.
     *
     * A reference to it names another company, and inCallerGroup() is what
     * holds that company inside the caller's parent-subsidiary group.
     */
    private const ORGANIZATION_TABLE = 'organizations';

    /**
     * References left unscoped, keyed by "<path under app/Http>:<table>",
     * with how many that file holds and why they cannot be narrowed.
     *
     * @var array<string, array{count: int, reason: string}>
     */
    private const UNSCOPED = [
        'Controllers/Api/V1/Admin/PlatformAdminController.php:organizations' => [
            'count' => 1,
            'reason' => 'A platform admin sets the parent of any tenant and holds no organization of their own, so there is no caller group to narrow the choice to.',
        ],
        'Controllers/Api/V1/Core/CustomerPortalController.php:organizations' => [
            'count' => 2,
            'reason' => 'Portal sign-in and password reset are unauthenticated, so there is no caller whose group would narrow the organization.',
        ],
        'Requests/Auth/RegisterRequest.php:users' => [
            'count' => 1,
            'reason' => 'Registration is unauthenticated, so there is no caller organization to scope the inviting user to.',
        ],
    ];

    public function test_request_rules_scope_tenant_owned_references(): void
    {
        $references = $this->referencesIn($this->phpFilesIn('Http'));

        $this->assertGreaterThan(100, count($references),
            'Found too few exists rules; the app/Http scan is not working.');

        $unscoped = $this->unscoped($references);

        $this->assertMissingTablesAreNone($unscoped);

        $found = [];

        foreach ($unscoped as $reference) {
            if ($this->isTenantOwned($reference['table'])) {
                $key = $reference['file'].':'.$reference['table'];
                $found[$key] = ($found[$key] ?? 0) + 1;
            }
        }

        $this->assertCountsMatch(array_map(fn (array $entry) => $entry['count'], self::UNSCOPED), $found,
            "The unscoped references to tenant-owned tables have changed.\n"
            ."Replace the rule with \$this->ownedBy('<table>'), or with "
            ."\$this->ownedThrough('<table>', '<foreign key>', '<parent table>') when the row is "
            ."reached through a parent that carries the organization, or with "
            ."\$this->inCallerGroup() when the field names another company, from "
            ."App\\Http\\Concerns\\ValidatesOwnedRows.\n"
            .'When a reference genuinely cannot be scoped, list it in UNSCOPED with the reason.');
    }

    public function test_every_unscoped_reference_gives_a_reason(): void
    {
        foreach (self::UNSCOPED as $reference => $entry) {
            $this->assertNotSame('', trim($entry['reason']), "{$reference} is listed without a reason.");
            $this->assertGreaterThan(0, $entry['count'], "{$reference} is listed with no reference to exempt.");
        }
    }

    public function test_detector_reads_unscoped_references_only(): void
    {
        $directory = (string) realpath(__DIR__.'/../../../app/Http');

        $this->assertDirectoryExists($directory, 'app/Http was not found, so the probe has nowhere to live.');

        $probe = $directory.DIRECTORY_SEPARATOR.'OwnedReferenceRuleProbe.php';

        file_put_contents($probe, <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Http;

            use Illuminate\Validation\Rule;

            class OwnedReferenceRuleProbe
            {
                public function rules(): array
                {
                    return [
                        'product_id' => 'required|exists:products,id',
                        'currency_code' => 'required|exists:currencies,code',
                        'contact_id' => [
                            'required',
                            Rule::exists('contacts', 'id')->where('organization_id', 1),
                        ],
                        // 'warehouse_id' => 'required|exists:warehouses,id',
                    ];
                }
            }
            PHP);

        try {
            $references = $this->unscoped($this->referencesIn(['OwnedReferenceRuleProbe.php' => $probe]));

            $tenantOwned = array_values(array_filter(
                $references,
                fn (array $reference) => $this->isTenantOwned($reference['table'])
            ));

            $this->assertSame(['products'], array_column($tenantOwned, 'table'),
                'The detector should report the unscoped rule on products, and only that one: '
                .'currencies carries no organization column, the contacts rule scopes itself, '
                .'and the commented-out warehouses rule is not code.');
        } finally {
            unlink($probe);
        }
    }

    /**
     * Every exists reference in the given files, with the table it names and
     * whether it limits itself to the caller's organization.
     *
     * @param  array<string, string>  $files  path under app/Http => absolute path
     * @return list<array{file: string, table: string, scoped: bool}>
     */
    private function referencesIn(array $files): array
    {
        $references = [];

        foreach ($files as $relative => $path) {
            $code = $this->codeOf($path);

            // A trait is where a scoped rule is built, so its own
            // Rule::exists() calls are the helpers, not call sites.
            if (preg_match('/^trait\s+ValidatesOwnedRows\b/m', $code)) {
                continue;
            }

            foreach ($this->bareRuleTables($code) as $table) {
                $references[] = ['file' => $relative, 'table' => $table, 'scoped' => false];
            }

            foreach ($this->ruleExistsCalls($code) as [$table, $scoped]) {
                $references[] = ['file' => $relative, 'table' => $table, 'scoped' => $scoped];
            }
        }

        return $references;
    }

    /**
     * @param  list<array{file: string, table: string, scoped: bool}>  $references
     * @return list<array{file: string, table: string, scoped: bool}>
     */
    private function unscoped(array $references): array
    {
        return array_values(array_filter($references, fn (array $reference) => ! $reference['scoped']));
    }

    /**
     * The tables named by exists: string rules, which carry no condition.
     *
     * @return list<string>
     */
    private function bareRuleTables(string $code): array
    {
        preg_match_all('/(?<![\w:])exists:(\w+)/', $code, $matches);

        return $matches[1];
    }

    /**
     * Each Rule::exists() call with the table it names and whether it scopes itself.
     *
     * @return list<array{0: string, 1: bool}>
     */
    private function ruleExistsCalls(string $code): array
    {
        $namespace = $this->namespaceOf($code);
        $imports = $this->importsOf($code);

        preg_match_all('/(?<![\w\\\\$>])(\\\\?[A-Z][\w\\\\]*)\s*::\s*exists\s*\(/', $code, $calls,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $found = [];

        foreach ($calls as $call) {
            if ($this->resolveClass($call[1][0], $imports, $namespace) !== 'Illuminate\\Validation\\Rule') {
                continue;
            }

            $expression = substr($code, $call[0][1],
                $this->expressionEnd($code, $call[0][1] + strlen($call[0][0]) - 1) - $call[0][1]);

            $table = preg_match('/::\s*exists\s*\(\s*[\'"](\w+)[\'"]/', $expression, $name)
                ? $name[1]
                : '(table named by a variable)';

            $found[] = [$table, (bool) preg_match('/where\w*\s*\(\s*\'organization_id\'/', $expression)];
        }

        return $found;
    }

    /**
     * The offset just past a call that starts at the bracket $open, including
     * every ->method() chained onto it, so a rule written over several lines is
     * read as one expression.
     */
    private function expressionEnd(string $code, int $open): int
    {
        $end = $this->bracketEnd($code, $open);

        while (preg_match('/\A\s*->\s*\w+\s*\(/', substr($code, $end), $link)) {
            $end = $this->bracketEnd($code, $end + strlen($link[0]) - 1);
        }

        return $end;
    }

    /** The offset just past the bracket opened at $open, skipping string literals. */
    private function bracketEnd(string $code, int $open): int
    {
        $depth = 0;
        $length = strlen($code);

        for ($i = $open; $i < $length; $i++) {
            $character = $code[$i];

            if ($character === '"' || $character === "'") {
                for ($i++; $i < $length && $code[$i] !== $character; $i++) {
                    $i += $code[$i] === '\\' ? 1 : 0;
                }

                continue;
            }

            if (str_contains('([{', $character)) {
                $depth++;
            } elseif (str_contains(')]}', $character) && --$depth === 0) {
                return $i + 1;
            }
        }

        return $length;
    }

    private function isTenantOwned(string $table): bool
    {
        return $table === self::ORGANIZATION_TABLE
            || Schema::hasColumn($table, self::TENANT_COLUMN);
    }

    /**
     * Fails when an unscoped reference names a table the test schema does not
     * have - including one named by a variable - because its tenant column
     * cannot then be read and the check would pass on nothing.
     *
     * @param  list<array{file: string, table: string, scoped: bool}>  $references
     */
    private function assertMissingTablesAreNone(array $references): void
    {
        $missing = [];

        foreach ($references as $reference) {
            if (! Schema::hasTable($reference['table'])) {
                $missing[] = $reference['file'].': '.$reference['table'];
            }
        }

        $this->assertSame([], array_values(array_unique($missing)),
            'These rules name a table that is not in the schema, so whether it belongs to '
            .'one organization cannot be told. Correct the table name or add its migration.');
    }
}
