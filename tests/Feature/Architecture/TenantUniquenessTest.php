<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Architecture\Concerns\ScansSource;
use Tests\TestCase;

/**
 * Keeps a value one organization uses from being refused to another.
 *
 * A tenant-owned table carries organization_id, so a document number or a code
 * belongs to the organization that wrote it. A unique index over the value
 * alone, or a 'unique:invoices,number' rule with no organization condition,
 * ignores that: the second organization to number an invoice INV-0001 is
 * refused, and the refusal tells it the value exists somewhere it cannot see.
 *
 * Two checks run here.
 *
 * The index check reads the unique indexes of every table with an
 * organization_id column. An index is scoped when it names organization_id, and
 * also when it names a column that is a foreign key to another tenant-owned
 * table: the parent row belongs to one organization, so the child cannot
 * collide across organizations through it. GLOBAL_COLUMNS names the columns
 * whose value is drawn from one space for the whole installation, so an index
 * over one of them alone carries no organization by design.
 *
 * The rule check reads every unique: string rule and Rule::unique() call under
 * app/Http. A string rule is scoped when its parameters name organization_id -
 * the form unique:<table>,<column>,NULL,id,organization_id,<id> - and a
 * Rule::unique() call when its own expression, with the methods chained onto
 * it however many lines that spans, names organization_id in a where().
 *
 * Both lists are exact. A new unscoped index or rule on a tenant-owned table
 * fails until it is scoped or listed with a reason, and a listed entry that has
 * been scoped or removed fails until it is delisted.
 */
class TenantUniquenessTest extends TestCase
{
    use RefreshDatabase;
    use ScansSource;

    /** The column that marks a table as belonging to one organization. */
    private const TENANT_COLUMN = 'organization_id';

    /**
     * Columns holding a value unique across the whole installation, so a unique
     * index over one of them alone is global on purpose.
     *
     * @var array<string, string>
     */
    private const GLOBAL_COLUMNS = [
        'uuid' => 'A row\'s uuid is the handle the API answers with and routes accept, so it names one row wherever it is read, without an organization to read it in.',
    ];

    /**
     * Unique indexes deliberately left global, keyed by "<table>:<columns>",
     * each with the reason it cannot be narrowed to one organization.
     *
     * @var array<string, string>
     */
    private const GLOBAL_INDEXES = [
        'billing_invoices:invoice_number' => 'The platform bills the tenant and issues this number itself, from one series across every organization.',
        'billing_payments:transaction_id' => 'The reference of a payment the platform took for a subscription, issued outside any one organization.',
        'document_download_tokens:token' => 'The token is the whole of a download link and is resolved before any organization is known, so one value must never name two documents.',
        'invoices:compliance_uuid' => 'The e-invoicing authority assigns it, and the status of a submission is asked for by that value alone.',
        'number_sequences:sequence_key' => 'The key is built as "<organization>:<prefix>:<year>", so it already carries the organization and the counter is read by the key alone.',
        'price_check_stations:api_token' => 'A station presents the token as its only credential, before the organization it belongs to is known.',
        'support_tickets:ticket_number' => 'The platform support desk issues it, and the ticket outlives the organization it was raised for, which is set to null when that organization is deleted.',
        'users:email' => 'Sign-in finds the account by email alone, with no organization chosen yet.',
    ];

    /**
     * Unscoped unique rules, keyed by "<path under app/Http>:<table>", with how
     * many that file holds and why they cannot be narrowed.
     *
     * @var array<string, array{count: int, reason: string}>
     */
    private const GLOBAL_RULES = [
        'Controllers/Api/V1/Core/UserController.php:users' => [
            'count' => 2,
            'reason' => 'Sign-in finds the account by email alone, so an address may be used once across the installation.',
        ],
        'Requests/Auth/RegisterRequest.php:users' => [
            'count' => 1,
            'reason' => 'Registration is unauthenticated and creates the organization, so the address is checked before one exists.',
        ],
    ];

    public function test_unique_indexes_carry_the_organization(): void
    {
        $indexes = $this->uniqueIndexes();

        $this->assertGreaterThan(400, count($indexes),
            'Found too few unique indexes; the schema scan is not working.');

        $found = [];

        foreach ($this->unscopedIndexes($indexes) as $index) {
            $found[$index['table'].':'.implode(',', $index['columns'])] = true;
        }

        $found = array_keys($found);
        sort($found);

        $listed = array_keys(self::GLOBAL_INDEXES);
        sort($listed);

        $this->assertSame($listed, $found,
            "The unique indexes that do not carry the organization have changed.\n"
            .'Drop the index and create it again with organization_id as its first column, in a '
            ."migration for that module.\n"
            .'When a value must stay unique across the installation, list it in GLOBAL_INDEXES with the reason.');
    }

    public function test_unique_rules_scope_tenant_owned_tables(): void
    {
        $rules = $this->uniqueRulesIn($this->phpFilesIn('Http'));

        $this->assertGreaterThan(15, count($rules),
            'Found too few unique rules; the app/Http scan is not working.');

        $this->assertMissingTablesAreNone($rules);

        $found = [];

        foreach ($rules as $rule) {
            if (! $rule['scoped'] && $this->isTenantOwned($rule['table'])) {
                $key = $rule['file'].':'.$rule['table'];
                $found[$key] = ($found[$key] ?? 0) + 1;
            }
        }

        $this->assertCountsMatch(array_map(fn (array $entry) => $entry['count'], self::GLOBAL_RULES), $found,
            "The unscoped unique rules on tenant-owned tables have changed.\n"
            ."Add the organization to the rule: Rule::unique('<table>', '<column>')"
            ."->where('organization_id', \$organizationId), or the string form "
            ."unique:<table>,<column>,NULL,id,organization_id,<id>.\n"
            .'When a value must stay unique across the installation, list it in GLOBAL_RULES with the reason.');
    }

    public function test_every_global_entry_gives_a_reason(): void
    {
        $columnsAlone = [];

        foreach ($this->uniqueIndexes() as $index) {
            if (count($index['columns']) === 1) {
                $columnsAlone[$index['columns'][0]] = true;
            }
        }

        foreach (self::GLOBAL_COLUMNS as $column => $reason) {
            $this->assertNotSame('', trim($reason), "The global column {$column} is listed without a reason.");
            $this->assertArrayHasKey($column, $columnsAlone,
                "No tenant-owned table has a unique index on {$column} alone any more. Remove it from GLOBAL_COLUMNS.");
        }

        foreach (self::GLOBAL_INDEXES as $index => $reason) {
            $this->assertNotSame('', trim($reason), "{$index} is listed without a reason.");
        }

        foreach (self::GLOBAL_RULES as $rule => $entry) {
            $this->assertNotSame('', trim($entry['reason']), "{$rule} is listed without a reason.");
            $this->assertGreaterThan(0, $entry['count'], "{$rule} is listed with no rule to exempt.");
        }
    }

    public function test_the_index_detector_reads_unscoped_indexes_only(): void
    {
        Schema::create('tenant_uniqueness_probes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->uuid('uuid')->unique();
            $table->string('document_number');
            $table->string('bin_code');
            $table->string('name');

            $table->unique(['document_number'], 'probe_number_unq');
            $table->unique(['organization_id', 'name'], 'probe_org_name_unq');
            $table->unique(['warehouse_id', 'bin_code'], 'probe_warehouse_bin_unq');
        });

        try {
            $unscoped = array_values(array_filter(
                $this->unscopedIndexes($this->uniqueIndexes()),
                fn (array $index) => $index['table'] === 'tenant_uniqueness_probes'
            ));

            $this->assertSame([['document_number']], array_column($unscoped, 'columns'),
                'The detector should report the index on document_number, and only that one: '
                .'the uuid is global by column, the name is scoped by organization_id, and the bin code '
                .'is scoped through warehouse_id, which belongs to one organization.');
        } finally {
            Schema::drop('tenant_uniqueness_probes');
        }
    }

    public function test_the_rule_detector_reads_unscoped_rules_only(): void
    {
        $directory = (string) realpath(__DIR__.'/../../../app/Http');

        $this->assertDirectoryExists($directory, 'app/Http was not found, so the probe has nowhere to live.');

        $probe = $directory.DIRECTORY_SEPARATOR.'TenantUniquenessProbe.php';

        file_put_contents($probe, <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Http;

            use Illuminate\Validation\Rule;

            class TenantUniquenessProbe
            {
                public function rules(): array
                {
                    return [
                        'sku' => 'required|unique:products,sku',
                        'code' => 'required|unique:warehouses,code,NULL,id,organization_id,1',
                        'symbol' => 'required|unique:currencies,code',
                        'slug' => [
                            'required',
                            Rule::unique('categories', 'slug')->where('organization_id', 1),
                        ],
                        'barcode' => ['required', Rule::unique('product_barcodes', 'barcode_value')],
                        // 'name' => 'required|unique:contacts,name',
                    ];
                }
            }
            PHP);

        try {
            $rules = $this->uniqueRulesIn(['TenantUniquenessProbe.php' => $probe]);

            $unscoped = array_values(array_filter(
                $rules,
                fn (array $rule) => ! $rule['scoped'] && $this->isTenantOwned($rule['table'])
            ));

            $this->assertSame(['products', 'product_barcodes'], array_column($unscoped, 'table'),
                'The detector should report the unscoped rules on products and product_barcodes, and only '
                .'those: the warehouse rule names organization_id in its parameters, the category rule '
                .'scopes itself, currencies carries no organization column, and the commented-out '
                .'contacts rule is not code.');
        } finally {
            unlink($probe);
        }
    }

    /**
     * Every unique index of every tenant-owned table, with the columns it
     * covers and the table each of those columns points at.
     *
     * @return list<array{table: string, columns: list<string>, parents: array<string, string>}>
     */
    private function uniqueIndexes(): array
    {
        $tenantOwned = [];

        foreach (Schema::getTableListing(schema: null, schemaQualified: false) as $table) {
            if (Schema::hasColumn($table, self::TENANT_COLUMN)) {
                $tenantOwned[$table] = true;
            }
        }

        $indexes = [];

        foreach (array_keys($tenantOwned) as $table) {
            $parents = [];

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (isset($tenantOwned[$foreignKey['foreign_table']])) {
                    foreach ($foreignKey['columns'] as $column) {
                        $parents[$column] = $foreignKey['foreign_table'];
                    }
                }
            }

            foreach (Schema::getIndexes($table) as $index) {
                if ($index['unique'] && ! $index['primary']) {
                    $indexes[] = ['table' => $table, 'columns' => $index['columns'], 'parents' => $parents];
                }
            }
        }

        return $indexes;
    }

    /**
     * @param  list<array{table: string, columns: list<string>, parents: array<string, string>}>  $indexes
     * @return list<array{table: string, columns: list<string>, parents: array<string, string>}>
     */
    private function unscopedIndexes(array $indexes): array
    {
        return array_values(array_filter($indexes, function (array $index): bool {
            if (in_array(self::TENANT_COLUMN, $index['columns'], true)) {
                return false;
            }

            if (count($index['columns']) === 1 && isset(self::GLOBAL_COLUMNS[$index['columns'][0]])) {
                return false;
            }

            foreach ($index['columns'] as $column) {
                if (isset($index['parents'][$column])) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Every unique rule in the given files, with the table it names and whether
     * it limits itself to the caller's organization.
     *
     * @param  array<string, string>  $files  path under app/Http => absolute path
     * @return list<array{file: string, table: string, scoped: bool}>
     */
    private function uniqueRulesIn(array $files): array
    {
        $rules = [];

        foreach ($files as $relative => $path) {
            $code = $this->codeOf($path);

            foreach ($this->stringRules($code) as [$table, $scoped]) {
                $rules[] = ['file' => $relative, 'table' => $table, 'scoped' => $scoped];
            }

            foreach ($this->ruleUniqueCalls($code) as [$table, $scoped]) {
                $rules[] = ['file' => $relative, 'table' => $table, 'scoped' => $scoped];
            }
        }

        return $rules;
    }

    /**
     * Each unique: string rule with the table it names and whether its
     * parameters name organization_id.
     *
     * The parameters are read to the end of the rule, which is the end of the
     * array element the rule is written as, because a rule that ends in the
     * organization's id is built by concatenation and its later parameters lie
     * outside the string literal the rule starts in.
     *
     * @return list<array{0: string, 1: bool}>
     */
    private function stringRules(string $code): array
    {
        preg_match_all('/(?<![\w:])unique:(\w+)/', $code, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $found = [];

        foreach ($matches as $match) {
            $start = $match[0][1];
            $parameters = substr($code, $start, $this->ruleEnd($code, $start) - $start);

            $found[] = [$match[1][0], str_contains($parameters, self::TENANT_COLUMN)];
        }

        return $found;
    }

    /**
     * The offset at which the rule starting inside a string literal at $start
     * ends: the first comma, semicolon or closing bracket that is neither
     * inside a string nor inside brackets of its own.
     */
    private function ruleEnd(string $code, int $start): int
    {
        $quote = $code[$this->openingQuote($code, $start)];
        $depth = 0;
        $length = strlen($code);
        $inString = true;

        for ($i = $start; $i < $length; $i++) {
            $character = $code[$i];

            if ($inString) {
                $i += $character === chr(92) ? 1 : 0;
                $inString = $character !== $quote;

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $inString = true;
            } elseif (str_contains('([{', $character)) {
                $depth++;
            } elseif (str_contains(')]}', $character)) {
                if ($depth === 0) {
                    return $i;
                }

                $depth--;
            } elseif ($depth === 0 && ($character === ',' || $character === ';')) {
                return $i;
            }
        }

        return $length;
    }

    /** The offset of the quote that opened the string literal $start lies in. */
    private function openingQuote(string $code, int $start): int
    {
        for ($i = $start; $i > 0; $i--) {
            if ($code[$i] === '"' || $code[$i] === "'") {
                return $i;
            }
        }

        return 0;
    }

    /**
     * Each Rule::unique() call with the table it names and whether it scopes itself.
     *
     * @return list<array{0: string, 1: bool}>
     */
    private function ruleUniqueCalls(string $code): array
    {
        $namespace = $this->namespaceOf($code);
        $imports = $this->importsOf($code);

        preg_match_all('/(?<![\w\\\\$>])(\\\\?[A-Z][\w\\\\]*)\s*::\s*unique\s*\(/', $code, $calls,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $found = [];

        foreach ($calls as $call) {
            if ($this->resolveClass($call[1][0], $imports, $namespace) !== 'Illuminate\Validation\Rule') {
                continue;
            }

            $expression = substr($code, $call[0][1],
                $this->expressionEnd($code, $call[0][1] + strlen($call[0][0]) - 1) - $call[0][1]);

            $table = preg_match('/::\s*unique\s*\(\s*[\'"](\w+)[\'"]/', $expression, $name)
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
                    $i += $code[$i] === chr(92) ? 1 : 0;
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
        return Schema::hasColumn($table, self::TENANT_COLUMN);
    }

    /**
     * Fails when a rule names a table the test schema does not have - including
     * one named by a variable - because whether it belongs to one organization
     * cannot then be read and the check would pass on nothing.
     *
     * @param  list<array{file: string, table: string, scoped: bool}>  $rules
     */
    private function assertMissingTablesAreNone(array $rules): void
    {
        $missing = [];

        foreach ($rules as $rule) {
            if (! Schema::hasTable($rule['table'])) {
                $missing[] = $rule['file'].': '.$rule['table'];
            }
        }

        $this->assertSame([], array_values(array_unique($missing)),
            'These rules name a table that is not in the schema, so whether it belongs to '
            .'one organization cannot be told. Correct the table name or add its migration.');
    }
}
