<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * No new write endpoint may ship without an authorization guard.
 *
 * Authentication answers "who is this". Authorization answers "may they do
 * this". The API has both mechanisms - check.permission against the seeded
 * permission catalogue, plus super.admin, check.branch and ip.allowlist - but
 * they are applied to only some of the write endpoints. The fixture below is
 * the current count; a number repeated here would only go stale.
 *
 * On the rest, auth:api and check.organization are the only gates. That makes
 * every authenticated member of an organization equivalent to every other:
 * whoever can read a page can also post to it. hr/departments requires
 * hr.departments.create; hr/off-cycle-payroll, in the same module, requires
 * nothing. Nobody designed that.
 *
 * Closing it means choosing a permission for each endpoint, which is domain
 * work and not something a test can do. What a test can do is stop the set
 * growing, so the number only falls.
 */
class WriteAuthorizationTest extends TestCase
{
    private const BASELINE = __DIR__.'/../../Fixtures/unguarded-write-routes.txt';

    /**
     * Middleware that answers "may they do this", by any mechanism.
     *
     * verify.zatca.webhook belongs here: the caller is the tax authority, not
     * a user, and a signature it cannot forge is what authorizes the request.
     * There is no role to check and no permission that could be granted.
     */
    private const AUTHORIZATION = [
        'permission', 'super.admin', 'check.branch', 'ip.allowlist', 'can:',
        'verify.zatca.webhook',
    ];

    public function test_no_new_unguarded_write_endpoints(): void
    {
        $declared = $this->baseline();
        $this->assertNotEmpty($declared, 'The baseline fixture is missing or empty.');

        $found = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v1/')) {
                continue;
            }

            $middleware = strtolower(implode(' ', $route->gatherMiddleware()));

            foreach ($route->methods() as $verb) {
                if (! in_array($verb, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    continue;
                }

                foreach (self::AUTHORIZATION as $guard) {
                    if (str_contains($middleware, $guard)) {
                        continue 2;
                    }
                }

                $found[] = $verb.' '.$uri;
            }
        }

        $found = array_values(array_unique($found));
        sort($found);

        $added = array_values(array_diff($found, $declared));
        $closed = array_values(array_diff($declared, $found));

        $this->assertSame([], $added, sprintf(
            "These write endpoints have no authorization guard and are not in the "
            ."baseline. Any authenticated member of the organization can call them, "
            ."whatever role they hold. Add check.permission with a seeded permission, "
            ."or add the route to the fixture and say why.\n%s",
            implode("\n", $added)
        ));

        $this->assertSame([], $closed, sprintf(
            "These are guarded now, which is the goal - remove them from\n%s\nso the "
            ."baseline keeps shrinking.\n%s",
            'tests/Fixtures/unguarded-write-routes.txt',
            implode("\n", $closed)
        ));
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
