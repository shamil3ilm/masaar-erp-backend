<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Keeps raw access to tenant tables deliberate.
 *
 * BelongsToOrganization applies a global scope, and that is an Eloquent
 * feature. DB::table() never passes through it, so a raw query on a table
 * carrying organization_id sits outside the isolation guarantee and is correct
 * only for as long as whoever wrote it remembered a where.
 *
 * 770 of the tables carry organization_id. Twenty-eight files query one
 * directly, and most are right to: a platform dashboard spans tenants by
 * definition, an audit-log cleanup runs across all of them, and hydrating
 * models to produce a COUNT is waste.
 *
 * So the rule is not "never raw". It is that each instance is a decision
 * somebody made, and a new one cannot arrive unnoticed. The list below is a
 * ratchet: the assertion is equality, so adding a raw tenant query fails the
 * build until the file is listed, and removing one fails until it is delisted.
 *
 * What this deliberately does not claim is that the twenty-eight are safe.
 * Telling a genuine cross-tenant read from a find() on an id the surrounding
 * code already scoped needs reading the code, not a pattern - an earlier
 * attempt to classify them automatically produced false positives on exactly
 * that distinction.
 */
class RawTenantQueryTest extends TestCase
{
    private const APP = __DIR__.'/../../../app';

    private const MIGRATIONS = __DIR__.'/../../../database/migrations';

    /**
     * Files that query a tenant table through the query builder.
     *
     * Adding a file here is the act of declaring the query cross-tenant,
     * console-only, or scoped by hand. If the reason is "it was easier", use
     * the model instead.
     */
    private const DECLARED = [
        'Console/Commands/CleanupAuditLogs.php',
        'Http/Controllers/Api/V1/Core/SensitiveAccessController.php',
        'Http/Controllers/Api/V1/Core/UserEventsController.php',
        'Http/Controllers/Api/V1/Inventory/PickingListController.php',
        'Services/Accounting/AgingReportService.php',
        'Services/Accounting/AssessmentCycleService.php',
        'Services/Accounting/CopaService.php',
        'Services/Accounting/DistributionCycleService.php',
        'Services/Accounting/DunningService.php',
        'Services/Admin/SuperAdminDashboardService.php',
        'Services/Aml/AmlMonitoringService.php',
        'Services/Analytics/DataWarehouseService.php',
        'Services/Analytics/UserClusteringService.php',
        'Services/Campaign/ConditionEvaluator.php',
        'Services/Compliance/RiskManagementService.php',
        'Services/Core/ApprovalWorkflowService.php',
        'Services/Core/ArchiveService.php',
        'Services/Core/FinancialIdempotencyService.php',
        'Services/Core/ModuleReadinessService.php',
        'Services/Core/NumberGeneratorService.php',
        'Services/Core/UserLifecycleService.php',
        'Services/Fraud/FraudRuleEngine.php',
        'Services/HR/PayrollService.php',
        'Services/Inventory/ReorderPointService.php',
        'Services/Reports/InventoryReportService.php',
        'Services/Reports/SalesReportService.php',
        'Services/Sales/AtpService.php',
        'Services/Sales/OutputDeterminationService.php',
    ];

    public function test_raw_tenant_queries_are_declared(): void
    {
        $tenantTables = $this->tenantTables();

        $this->assertGreaterThan(100, count($tenantTables),
            'Found too few tenant tables; the migration scan is not working.');

        $found = [];

        foreach ($this->phpFiles() as $path) {
            $source = (string) file_get_contents($path);

            preg_match_all("/DB::table\(\s*'([a-z_0-9]+)'/", $source, $matches);

            foreach ($matches[1] as $table) {
                if (isset($tenantTables[$table])) {
                    $found[$this->relative($path)] = true;
                    break;
                }
            }
        }

        $found = array_keys($found);
        sort($found);

        $expected = self::DECLARED;
        sort($expected);

        $this->assertSame($expected, $found, sprintf(
            "The set of files raw-querying a tenant table has changed.\n"
            ."New ones bypass BelongsToOrganization's global scope, so isolation "
            ."there depends on a where somebody has to remember. Use the model, or "
            ."add the file to DECLARED and say why.\nAdded: %s\nGone: %s",
            implode(', ', array_diff($found, $expected)) ?: 'none',
            implode(', ', array_diff($expected, $found)) ?: 'none'
        ));
    }

    /** @return array<string, true> */
    private function tenantTables(): array
    {
        $all = '';

        foreach (glob(self::MIGRATIONS.'/*.php') ?: [] as $file) {
            $all .= (string) file_get_contents($file)."\n";
        }

        preg_match_all("/Schema::create\('([a-z_0-9]+)'.*?\n(.*?)\n        \}\);/s", $all, $blocks, PREG_SET_ORDER);

        $tables = [];

        foreach ($blocks as $block) {
            if (str_contains($block[2], 'organization_id')) {
                $tables[$block[1]] = true;
            }
        }

        return $tables;
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        $out = [];

        $tree = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(realpath(self::APP), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($tree as $file) {
            if ($file->getExtension() === 'php') {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    private function relative(string $path): string
    {
        return str_replace(
            [realpath(self::APP).DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR],
            ['', '/'],
            $path
        );
    }
}
