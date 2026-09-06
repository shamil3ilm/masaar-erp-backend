<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A permission must belong to the module whose code it guards.
 *
 * Route names are not evidence of that. They carry SAP codes — mm, wm, sd, fi
 * — which do not track where the code lives: the batch controllers are named
 * mm.* and sit in Inventory, and the material ledger is named mm.ml.* and sits
 * in Accounting. Both were first filed under purchase, which would have meant
 * granting a purchasing permission to close an accounting period. Nothing
 * would have failed; the endpoint refuses the wrong people quietly.
 *
 * The controller's namespace is the reliable signal, so this compares the two
 * and fails when a new pair disagrees.
 *
 * Disagreeing is not always wrong. A permission names the data, not the folder
 * the controller sits in: printing an invoice is guarded by
 * sales.invoices.view even though PrintController is Core, and the financial
 * report endpoints are accounting.reports.view on a Reports controller. Those
 * are listed below. The list is a ratchet, not an allowance — a new entry
 * fails the build and has to be argued for.
 */
class PermissionModuleTest extends TestCase
{
    /** Controller namespace => the module that owns its permissions. */
    private const OWNER = [
        'RealEstate' => 'real_estate', 'TaskBoard' => 'taskboard', 'Tm' => 'tm',
        'Hr' => 'hr', 'Sales' => 'sales', 'Purchase' => 'purchase',
        'Inventory' => 'inventory', 'Accounting' => 'accounting',
        'Manufacturing' => 'manufacturing', 'Maintenance' => 'maintenance',
        'Projects' => 'projects', 'Core' => 'core', 'Crm' => 'crm',
        'Billing' => 'billing', 'Budget' => 'budget', 'Calendar' => 'calendar',
        'Compliance' => 'compliance', 'Customs' => 'customs',
        'Document' => 'documents', 'Ecommerce' => 'ecommerce',
        'Expense' => 'expenses', 'Loyalty' => 'loyalty',
        'Messaging' => 'messaging', 'Reports' => 'reports', 'Tax' => 'tax',
        'Trade' => 'trade', 'Automation' => 'automation',
        'Analytics' => 'analytics', 'Aml' => 'aml', 'Fraud' => 'fraud',
    ];

    /** Permissions that name the data rather than the controller's folder. */
    private const ACCEPTED = [
        'accounting.reports.view on Reports',
        'crm.contacts.view on Sales',
        'documents.files.manage on Core',
        'features.view on Core',
        'inventory.reports.view on Reports',
        'modules.manage on Core',
        'purchase.orders.view on Core',
        'sales.invoices.view on Core',
        'sales.payments.view on Core',
        'sales.quotations.view on Core',
        'sales.reports.view on Reports',
    ];

    public function test_permissions_match_their_controller(): void
    {
        $found = [];

        foreach (Route::getRoutes() as $route) {
            $controller = $route->getAction('controller');

            if (! is_string($controller) || ! str_contains($controller, '\\')) {
                continue;
            }

            $parts = explode('\\', explode('@', $controller)[0]);
            $namespace = $parts[count($parts) - 2] ?? '';
            $owner = self::OWNER[$namespace] ?? null;

            if ($owner === null) {
                continue;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'check.permission:')) {
                    continue;
                }

                foreach (preg_split('/[,|]/', substr($middleware, strlen('check.permission:'))) as $slug) {
                    $slug = trim($slug);

                    if ($slug !== '' && explode('.', $slug)[0] !== $owner) {
                        $found[] = "{$slug} on {$namespace}";
                    }
                }
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        $this->assertSame(self::ACCEPTED, $found, sprintf(
            "A permission is guarding a controller that belongs to another module.\n"
            ."Check which module owns the behaviour before adding it below - the "
            ."route name is not evidence, because the SAP prefixes in route names "
            ."do not track where the code lives.\n%s",
            implode("\n", array_diff($found, self::ACCEPTED))
        ));
    }
}
