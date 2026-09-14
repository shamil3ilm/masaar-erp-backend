<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\JournalEntryFactory;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PeriodLockService;
use App\Services\Tax\TaxCalculatorService;
use PHPUnit\Framework\TestCase;
use Tests\Feature\Architecture\Concerns\ScansSource;

/**
 * Keeps each module's services from reaching into other modules' services.
 *
 * Services live under app/Services/<Module>. When a Sales service calls an
 * Inventory service directly, the two modules can no longer change, be tested
 * or be switched off independently, and the flow that spans them is spread
 * across both instead of being written down in one place. The codebase already
 * has that place: app/Orchestrators, where PostInvoiceOrchestrator drives
 * posting, stock deduction, credit checks and compliance for an invoice.
 *
 * A dependency counts when a service file imports, or names fully qualified, a
 * class under another module's App\Services namespace. Some dependencies are
 * shared by design and do not count; SHARED_MODULES and SHARED_SERVICES name
 * them with the reason.
 *
 * EDGES lists the module-to-module dependencies that exist today. It is exact:
 * a new edge fails until it moves to an orchestrator or is listed, and a listed
 * edge that disappears fails until it is removed.
 */
class ServiceCouplingTest extends TestCase
{
    use ScansSource;

    /**
     * Modules whose services every module may use, with the reason.
     *
     * @var array<string, string>
     */
    private const SHARED_MODULES = [
        'Core' => 'Platform services the other modules are built on: document numbering, approval '
            .'workflows, financial idempotency, notifications, attachments and caching.',
        'Security' => 'Holds only IdempotencyService, a request-key lock and response store with no business rules.',
    ];

    /**
     * Classes every module may use from a module that is otherwise not shared.
     *
     * Accounting is not shared as a whole: its dunning, treasury or asset
     * services belong to Accounting. Its posting interface is, because every
     * financial document has to reach the general ledger.
     *
     * @var array<class-string, string>
     */
    private const SHARED_SERVICES = [
        JournalService::class => 'The general-ledger posting interface; every financial document posts its entry through it.',
        JournalEntryFactory::class => 'Builds the journal entry for Sales, Purchase and HR documents so account mapping lives in one place.',
        AccountResolver::class => 'Finds the account for a posting role while those entries are built.',
        PeriodLockService::class => 'Answers whether a posting date falls in a locked accounting period.',
        TaxCalculatorService::class => 'Tax arithmetic from configured rates, the same for sales and purchase documents.',
    ];

    /** Directories under app/Services holding support code rather than a module. */
    private const NOT_MODULES = ['Concerns'];

    /**
     * Module-to-module service dependencies that exist today.
     *
     * @var list<string>
     */
    private const EDGES = [
        'Core -> Auth',
        'Core -> Campaign',
        'Core -> HR',
        'Core -> Inventory',
        'Export -> Reports',
        'Maintenance -> Inventory',
        'Manufacturing -> Inventory',
        'Manufacturing -> Purchase',
        'Purchase -> Inventory',
        'Sales -> Accounting',
        'Sales -> Compliance',
        'Sales -> Inventory',
    ];

    public function test_services_stay_inside_their_module(): void
    {
        $edges = [];
        $sharedUses = 0;

        foreach ($this->phpFilesIn('Services') as $relative => $path) {
            $from = explode('/', $relative)[0];

            if (! str_contains($relative, '/') || in_array($from, self::NOT_MODULES, true)) {
                continue;
            }

            foreach ($this->serviceReferences($this->codeOf($path)) as $class) {
                $to = explode('\\', $class)[2];

                if ($to === $from || in_array($to, self::NOT_MODULES, true)) {
                    continue;
                }

                if (isset(self::SHARED_MODULES[$to]) || isset(self::SHARED_SERVICES[$class])) {
                    $sharedUses++;

                    continue;
                }

                $edges["{$from} -> {$to}"][$relative] = true;
            }
        }

        $this->assertGreaterThan(0, $sharedUses,
            'No service uses a shared module or class, so the import scan is not working.');

        ksort($edges);

        $listed = self::EDGES;
        sort($listed);

        $new = array_map(
            fn (string $edge): string => $edge.' ('.implode(', ', array_keys($edges[$edge])).')',
            array_values(array_diff(array_keys($edges), $listed))
        );

        $this->assertSame($listed, array_keys($edges), sprintf(
            "The dependencies between service modules have changed.\n"
            .'A service should call its own module and the shared services only. Work that spans '
            .'modules belongs in an orchestrator under app/Orchestrators, as '
            .'Sales/PostInvoiceOrchestrator does for posting an invoice. If the class you need is '
            ."shared by design, add it to SHARED_SERVICES and say why.\n"
            ."New: %s\nListed but gone: %s",
            implode('; ', $new) ?: 'none',
            implode(', ', array_diff($listed, array_keys($edges))) ?: 'none'
        ));
    }

    public function test_every_exemption_gives_a_reason(): void
    {
        foreach (self::SHARED_MODULES as $module => $reason) {
            $this->assertDirectoryExists(dirname(__DIR__, 3).'/app/Services/'.$module, "Shared module {$module} does not exist.");
            $this->assertNotSame('', trim($reason), "Shared module {$module} is listed without a reason.");
        }

        foreach (self::SHARED_SERVICES as $class => $reason) {
            $this->assertTrue(class_exists($class), "Shared service {$class} does not exist.");
            $this->assertNotSame('', trim($reason), "Shared service {$class} is listed without a reason.");
        }
    }

    /**
     * Classes under App\Services that the file imports or names fully qualified.
     *
     * @return list<string>
     */
    private function serviceReferences(string $code): array
    {
        $body = (string) preg_replace('/^(?:namespace|use)\s[^;]*;/m', '', $code);

        preg_match_all('/(?<![\w\\\\])\\\\?(App\\\\Services\\\\[\w\\\\]+)/', $body, $inline);

        $classes = array_filter(
            [...array_values($this->importsOf($code)), ...$inline[1]],
            fn (string $class): bool => str_starts_with($class, 'App\\Services\\') && substr_count($class, '\\') >= 3,
        );

        return array_values(array_unique($classes));
    }
}
