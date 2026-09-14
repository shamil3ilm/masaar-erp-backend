<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\Sales\Invoice;
use PHPUnit\Framework\TestCase;
use Tests\Feature\Architecture\Concerns\ScansSource;

/**
 * Keeps status changes on state-machine models going through transitionTo().
 *
 * A model using HasStateMachine declares in getStateTransitions() which status
 * may follow which. transitionTo() refuses any other move and runs the model's
 * onBefore and onAfter hooks. Writing the state column directly skips both, so
 * a paid invoice can return to draft and nothing that hangs off the hooks runs.
 *
 * Outside the model's own file, a write counts when it is ->update([...]),
 * ->updateQuietly([...]), ->fill([...]) or ->forceFill([...]) with the state
 * column as a top-level key, or an assignment ->status = ... . The write is tied
 * to a state-machine model in either of two ways:
 *   - its receiver is one: a variable whose nearest earlier binding is a
 *     parameter or property typed as the model or an assignment from
 *     Model::... or new Model, or a query chain started on the model class
 *   - its value names one of the model's constants, as Invoice::STATUS_PAID does
 *
 * It misses a write whose receiver is untyped and whose value is a bare string,
 * and a write made through a relation, such as $order->invoice()->update(...).
 * It cannot tell a record that is not saved yet, so giving a replicated record
 * its initial status counts too.
 *
 * BYPASSES is exact per file. A new direct write fails until it goes through
 * transitionTo() or is listed; a count that drops fails until it is lowered.
 */
class StateTransitionTest extends TestCase
{
    use ScansSource;

    /** Builder calls a chain can pass through and still act on the class it started on. */
    private const BUILDER_STEPS = 'query|where\w*|orWhere\w*|find\w*|first\w*|lockForUpdate|sharedLock|'
        .'withoutGlobalScopes?|withTrashed|onlyTrashed|latest|oldest|orderBy\w*|limit|take|inState';

    /** Words that can stand before a variable without typing it. */
    private const NOT_TYPES = [
        'return', 'yield', 'echo', 'print', 'throw', 'clone', 'else', 'case', 'global', 'static', 'as',
        'instanceof', 'new', 'use', 'and', 'or', 'xor', 'include', 'require', 'include_once',
        'require_once', 'fn', 'function', 'match', 'default', 'do', 'var', 'public', 'protected',
        'private', 'readonly', 'null', 'mixed', 'object', 'array', 'string', 'int', 'float', 'bool',
    ];

    /**
     * Files that write a state-machine model's status directly, with how many times.
     *
     * @var array<string, int>
     */
    private const BYPASSES = [
        'Http/Controllers/Api/V1/Sales/QuotationController.php' => 3,
        'Orchestrators/Sales/PostInvoiceOrchestrator.php' => 1,
        'Services/Accounting/CashDiscountService.php' => 1,
        'Services/Accounting/OpenItemClearingService.php' => 4,
        'Services/Core/CustomerPortalService.php' => 2,
        'Services/Core/RecurringTransactionService.php' => 2,
        'Services/HR/EmployeeService.php' => 1,
        'Services/HR/LeaveService.php' => 4,
        'Services/HR/PayrollService.php' => 3,
        'Services/Inventory/StockAdjustmentService.php' => 2,
        'Services/Manufacturing/WorkOrderService.php' => 1,
        'Services/Purchase/BillService.php' => 3,
        'Services/Purchase/GoodsReceiptService.php' => 2,
        'Services/Purchase/PurchaseOrderService.php' => 6,
        'Services/Sales/InvoiceConversionService.php' => 1,
        'Services/Sales/InvoiceService.php' => 1,
        'Services/Sales/SalesOrderDeliveryService.php' => 1,
    ];

    public function test_status_changes_go_through_transitions(): void
    {
        $machines = $this->stateMachines();

        $this->assertArrayHasKey(Invoice::class, $machines,
            'Invoice was not recognised as a HasStateMachine model, so the model scan is not working.');

        $ownFiles = [...array_column($machines, 'file'), 'Models/Concerns/HasStateMachine.php'];
        $found = [];

        foreach ($this->phpFilesIn('') as $relative => $path) {
            if (in_array($relative, $ownFiles, true)) {
                continue;
            }

            $count = $this->countBypasses($this->codeOf($path), $machines);

            if ($count > 0) {
                $found[$relative] = $count;
            }
        }

        $this->assertCountsMatch(self::BYPASSES, $found,
            "The direct status writes on state-machine models have changed.\n"
            .'Change status with $model->transitionTo($status, $otherColumns): it checks '
            .'getStateTransitions() and runs the onBefore/onAfter hooks. A write that must '
            .'bypass the rules belongs in the model, next to them. When a count drops, lower '
            .'it here; when a file reaches zero, remove it.');
    }

    public function test_detector_sees_each_bypass_form(): void
    {
        $code = $this->withoutComments(<<<'PHP'
            <?php

            namespace App\Services\Example;

            use App\Models\Sales\CreditNote;
            use App\Models\Sales\Invoice as SalesInvoice;

            class Example
            {
                public function __construct(private readonly SalesInvoice $current) {}

                public function counted(SalesInvoice $invoice, $untyped): void
                {
                    $invoice->update(['status' => 'paid']);
                    $invoice->status = 'sent';
                    $this->current->fill(['notes' => 'x', 'status' => 'void']);
                    SalesInvoice::where('id', 1)->lockForUpdate()->update(['status' => 'void']);
                    $untyped->forceFill(['status' => SalesInvoice::STATUS_PAID]);
                    $found = SalesInvoice::findOrFail(1);
                    $found->updateQuietly([
                        'status' => 'overdue',
                    ]);
                }

                public function ignored(SalesInvoice $invoice, CreditNote $note): void
                {
                    $invoice->transitionTo('paid');
                    $invoice->update(['notes' => 'status']);
                    $invoice->update(['meta' => ['status' => 'x']]);
                    $invoice->payments()->update(['status' => 'void']);
                    $note->update(['status' => 'applied']);
                    // $invoice->update(['status' => 'paid']);
                    if ($invoice->status === 'paid') {
                        $invoice = $note;
                        $invoice->update(['status' => 'applied']);
                    }
                }
            }
            PHP);

        $machines = [Invoice::class => ['column' => 'status', 'file' => '']];

        $this->assertSame(6, $this->countBypasses($code, $machines));
    }

    /**
     * Models using HasStateMachine, with their state column and file.
     *
     * @return array<class-string, array{column: string, file: string}>
     */
    private function stateMachines(): array
    {
        $machines = [];

        foreach ($this->phpFilesIn('Models') as $relative => $path) {
            $code = $this->codeOf($path);

            if (! preg_match('/^(?:abstract\s+|final\s+|readonly\s+)*class\s+(\w+)/m', $code, $class, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $body = substr($code, $class[0][1]);

            if (! preg_match('/^\s*use\s+[^;]*\bHasStateMachine\b[^;]*;/m', $body)) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/function\s+getStateColumn\s*\(\s*\)\s*:\s*string\s*\{\s*return\s+[\'"]\w+[\'"]\s*;/',
                $body,
                "Models/{$relative} uses HasStateMachine but its getStateColumn() does not return a literal column name."
            );

            preg_match('/function\s+getStateColumn\s*\(\s*\)\s*:\s*string\s*\{\s*return\s+[\'"](\w+)[\'"]/', $body, $column);

            $machines[$this->namespaceOf($code).'\\'.$class[1][0]] = ['column' => $column[1], 'file' => 'Models/'.$relative];
        }

        return $machines;
    }

    /** @param  array<class-string, array{column: string, file: string}>  $machines */
    private function countBypasses(string $code, array $machines): int
    {
        $namespace = $this->namespaceOf($code);
        $imports = $this->importsOf($code);
        $count = 0;

        foreach ($this->stateWrites($code, array_column($machines, 'column')) as [$arrow, $column, $value]) {
            $model = $this->receiverModel($code, $arrow, $imports, $namespace, $machines)
                ?? $this->valueModel($value, $imports, $namespace, $machines);

            if ($model !== null && $machines[$model]['column'] === $column) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Writes to any of the columns, as the offset of the -> they hang off,
     * the column written and the expression written to it.
     *
     * @param  list<string>  $columns
     * @return list<array{int, string, string}>
     */
    private function stateWrites(string $code, array $columns): array
    {
        $names = implode('|', array_map(fn (string $column): string => preg_quote($column, '/'), array_unique($columns)));
        $writes = [];

        preg_match_all('/->\s*(?:update|updateQuietly|fill|forceFill)\s*\(\s*\[/', $code, $calls, PREG_OFFSET_CAPTURE);

        foreach ($calls[0] as [$match, $offset]) {
            $open = $offset + strlen($match) - 1;
            $entries = $this->topLevel(substr($code, $open + 1, $this->closing($code, $open) - $open - 1));

            if (preg_match('/[\'"]('.$names.')[\'"]\s*=>\s*([^,]*)/', $entries, $entry)) {
                $writes[] = [$offset, $entry[1], $entry[2]];
            }
        }

        preg_match_all('/->\s*('.$names.')\s*=(?![=>])([^;]*)/', $code, $assignments, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($assignments as $assignment) {
            $writes[] = [$assignment[0][1], $assignment[1][0], $assignment[2][0]];
        }

        return $writes;
    }

    /**
     * @param  array<string, string>  $imports
     * @param  array<class-string, array{column: string, file: string}>  $machines
     */
    private function receiverModel(string $code, int $arrow, array $imports, string $namespace, array $machines): ?string
    {
        $chain = $this->chainBefore($code, $arrow);

        if ($chain === null) {
            return null;
        }

        [$root, $steps] = $chain;

        if (str_ends_with($root, '::')) {
            foreach ($steps as $step) {
                if (! preg_match('/^(?:'.self::BUILDER_STEPS.')\(\)$/', $step)) {
                    return null;
                }
            }

            $type = substr($root, 0, -2);
        } elseif ($root === '$this' && count($steps) === 1 && ! str_ends_with($steps[0], '()')) {
            $type = $this->propertyType($code, $steps[0]);
        } elseif ($root !== '$this' && $steps === []) {
            $type = $this->variableType($code, substr($root, 1), $arrow);
        } else {
            return null;
        }

        $class = $type === null ? null : $this->resolveClass($type, $imports, $namespace);

        return isset($machines[$class]) ? $class : null;
    }

    /**
     * @param  array<string, string>  $imports
     * @param  array<class-string, array{column: string, file: string}>  $machines
     */
    private function valueModel(string $value, array $imports, string $namespace, array $machines): ?string
    {
        preg_match_all('/(?<![\w\\\\$])(\\\\?[A-Za-z_][\w\\\\]*)::[A-Z][A-Z0-9_]*\b(?!\s*\()/', $value, $constants);

        foreach ($constants[1] as $name) {
            $class = $this->resolveClass($name, $imports, $namespace);

            if (isset($machines[$class])) {
                return $class;
            }
        }

        return null;
    }

    /**
     * What the access at $arrow is made on: a root ('$name' or 'Class::') and
     * the steps between it and $arrow ('name' for a property, 'name()' for a call).
     *
     * @return array{string, list<string>}|null
     */
    private function chainBefore(string $code, int $arrow): ?array
    {
        $steps = [];
        $at = $arrow;

        while (true) {
            $j = $at - 1;

            if ($j >= 0 && $code[$j] === '?') {
                $j--;
            }

            $j = $this->skipSpaceBack($code, $j);
            $called = false;

            if ($j >= 0 && $code[$j] === ')') {
                $j = $this->skipSpaceBack($code, $this->opening($code, $j) - 1);
                $called = true;
            }

            $end = $j;

            while ($j >= 0 && (ctype_alnum($code[$j]) || $code[$j] === '_')) {
                $j--;
            }

            $name = $end < 0 ? '' : substr($code, $j + 1, $end - $j);

            if ($name === '') {
                return null;
            }

            if ($j >= 0 && $code[$j] === '$') {
                return $called ? null : ['$'.$name, $steps];
            }

            array_unshift($steps, $name.($called ? '()' : ''));

            $k = $this->skipSpaceBack($code, $j);

            if ($k >= 1 && $code[$k - 1] === '-' && $code[$k] === '>') {
                $at = $k - 1;

                continue;
            }

            if ($k >= 1 && $code[$k - 1] === ':' && $code[$k] === ':') {
                $c = $this->skipSpaceBack($code, $k - 2);
                $classEnd = $c;

                while ($c >= 0 && (ctype_alnum($code[$c]) || $code[$c] === '_' || $code[$c] === '\\')) {
                    $c--;
                }

                $class = substr($code, $c + 1, $classEnd - $c);

                return $class === '' ? null : [$class.'::', $steps];
            }

            return null;
        }
    }

    /** The type of $variable at its nearest binding before $before, if that binding names one. */
    private function variableType(string $code, string $variable, int $before): ?string
    {
        $name = preg_quote($variable, '/');
        $bindings = [];

        preg_match_all('/(\\\\?[A-Za-z_][\w\\\\]*)\s+&?(?:\.\.\.)?\$'.$name.'\b/', $code, $typed, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($typed as $match) {
            if (! in_array(strtolower($match[1][0]), self::NOT_TYPES, true)) {
                $bindings[$match[0][1]] = $match[1][0];
            }
        }

        preg_match_all('/\$'.$name.'\s*=(?![=>])\s*(.{0,120})/s', $code, $assigned, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($assigned as $match) {
            $expression = $match[1][0];

            // Reloading keeps the type: $invoice = $invoice->fresh().
            if (preg_match('/^\$'.$name.'\s*->/', $expression)) {
                continue;
            }

            $bindings[$match[0][1]] = preg_match('/^(?:new\s+)?(\\\\?[A-Za-z_][\w\\\\]*)\s*(?:::|\()/', $expression, $class)
                ? $class[1]
                : null;
        }

        preg_match_all('/\bas\s+(?:&?\$\w+\s*=>\s*)?&?\$'.$name.'\b/', $code, $iterated, PREG_OFFSET_CAPTURE);

        foreach ($iterated[0] as [, $offset]) {
            $bindings[$offset] = null;
        }

        ksort($bindings);

        $type = null;

        foreach ($bindings as $offset => $binding) {
            if ($offset >= $before) {
                break;
            }

            $type = $binding;
        }

        return $type;
    }

    private function propertyType(string $code, string $property): ?string
    {
        $pattern = '/\b(?:public|protected|private|readonly)(?:\s+(?:public|protected|private|readonly|static))*'
            .'\s+\??(\\\\?[A-Za-z_][\w\\\\]*)\s+\$'.preg_quote($property, '/').'\b/';

        return preg_match($pattern, $code, $match) ? $match[1] : null;
    }

    /** The characters of $text outside any brackets or parentheses. */
    private function topLevel(string $text): string
    {
        $depth = 0;
        $out = '';

        foreach (str_split($text) as $char) {
            if ($char === '[' || $char === '(') {
                $depth++;
            }

            if ($depth === 0) {
                $out .= $char;
            }

            if ($char === ']' || $char === ')') {
                $depth--;
            }
        }

        return $out;
    }

    private function closing(string $code, int $open): int
    {
        $depth = 0;

        for ($i = $open, $length = strlen($code); $i < $length; $i++) {
            if ($code[$i] === '[' || $code[$i] === '(') {
                $depth++;
            } elseif (($code[$i] === ']' || $code[$i] === ')') && --$depth === 0) {
                return $i;
            }
        }

        return $open;
    }

    private function opening(string $code, int $close): int
    {
        $depth = 0;

        for ($i = $close; $i >= 0; $i--) {
            if ($code[$i] === ']' || $code[$i] === ')') {
                $depth++;
            } elseif (($code[$i] === '[' || $code[$i] === '(') && --$depth === 0) {
                return $i;
            }
        }

        return -1;
    }

    private function skipSpaceBack(string $code, int $at): int
    {
        while ($at >= 0 && ctype_space($code[$at])) {
            $at--;
        }

        return $at;
    }
}
