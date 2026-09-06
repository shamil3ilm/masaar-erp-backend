<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Gating a read must never cost the Viewer role its access.
 *
 * The Viewer role is seeded as every permission ending in .view and described
 * as read-only access to all features. So a read guarded by anything else -
 * a .manage, a .create, an .edit - is a read a viewer cannot perform, and the
 * role stops meaning what it says.
 *
 * Half the read endpoints were guarded by nothing at all, which made the
 * .view permissions decorative: an organization could not build a role that
 * reads less than everything, because the permission it would withhold was
 * not consulted. Gating them closed that, and this asserts the closing did
 * not quietly take reads away from the one role defined by having them.
 *
 * The list below is what already guarded a read with a non-view permission
 * before any of that. It is a ratchet, not an allowance.
 */
class ReadGateTest extends TestCase
{
    private const BASELINE = __DIR__.'/../../Fixtures/unguarded-read-routes.txt';

    /** Middleware that answers "may they do this", by any mechanism. */
    private const AUTHORIZATION = [
        'permission', 'super.admin', 'check.branch', 'ip.allowlist', 'can:',
        'verify.zatca.webhook',
    ];

    private const ACCEPTED = [
        'accounting.period-lock.manage',
        'core.change-freeze.manage',
        'core.feature-flags.manage',
        'core.settings.edit',
        'hr.payroll.export',
        'hr.training.reports',
        'manufacturing.quality.create',
        'manufacturing.quality.delete',
        'manufacturing.quality.edit',
        'sales.customer-material-infos.create',
        'sales.customer-material-infos.delete',
        'sales.customer-material-infos.edit',
        'sales.delivery-split-rules.create',
        'sales.delivery-split-rules.delete',
        'sales.delivery-split-rules.edit',
        'sales.output-types.create',
        'sales.output-types.delete',
        'sales.output-types.edit',
    ];

    public function test_reads_are_guarded_by_view_permissions(): void
    {
        $found = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'check.permission:')) {
                    continue;
                }

                foreach (preg_split('/[,|]/', substr($middleware, strlen('check.permission:'))) as $slug) {
                    $slug = trim($slug);

                    if ($slug !== '' && ! str_ends_with($slug, '.view')) {
                        $found[] = $slug;
                    }
                }
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        $this->assertSame(self::ACCEPTED, $found, sprintf(
            "A read endpoint is guarded by a permission the Viewer role does not "
            ."hold, so a role described as read-only cannot perform it. Use the "
            ."matching .view permission.\n%s",
            implode("\n", array_diff($found, self::ACCEPTED))
        ));
    }

    /**
     * And the set of reads with no guard at all only shrinks.
     */
    public function test_no_new_unguarded_read_endpoints(): void
    {
        $declared = [];

        foreach (file(self::BASELINE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (! str_starts_with($line, '#')) {
                $declared[] = $line;
            }
        }

        sort($declared);
        $this->assertNotEmpty($declared, 'The baseline fixture is missing or empty.');

        $found = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $middleware = strtolower(implode(' ', $route->gatherMiddleware()));

            foreach (self::AUTHORIZATION as $guard) {
                if (str_contains($middleware, $guard)) {
                    continue 2;
                }
            }

            $found[] = 'GET '.$route->uri();
        }

        $found = array_values(array_unique($found));
        sort($found);

        $this->assertSame([], array_values(array_diff($found, $declared)), sprintf(
            "These read endpoints have no authorization guard and are not in the "
            ."baseline. Any authenticated member of the organization can read "
            ."them.
%s",
            implode("
", array_diff($found, $declared))
        ));

        $this->assertSame([], array_values(array_diff($declared, $found)), sprintf(
            "These are guarded now - remove them from
%s
so the baseline keeps "
            ."shrinking.
%s",
            'tests/Fixtures/unguarded-read-routes.txt',
            implode("
", array_diff($declared, $found))
        ));
    }
}
