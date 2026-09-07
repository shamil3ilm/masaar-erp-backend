<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\Core\OrganizationModule;
use App\Models\Core\Permission;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * No endpoint may answer 500 to a request that reaches it.
 *
 * Most of this API is not covered by any other test, so a module can be
 * fatal on every request and nothing says so. A class that fails to load, a
 * missing method, a query the database rejects: none of it surfaces until
 * something calls the route.
 *
 * This will not tell you an endpoint is correct. It tells you the code behind
 * it parses, its dependencies resolve, and its query runs, which is the class
 * of failure that hides in an untested module. Every parameterless GET, as a
 * super admin with every module enabled, so what is being tested is the
 * handler rather than the gate in front of it.
 *
 * 4xx is fine and expected — missing filters, absent records, a feature that
 * needs configuration. Only 5xx counts.
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const BASELINE = __DIR__.'/../../Fixtures/failing-endpoints.txt';

    private function bootTenant(): void
    {
        // A thousand requests from one test trips the rate limiter, and a
        // 429 never reaches a handler. Left on, most of the pass is throttled
        // and the check goes green having exercised almost nothing.
        $this->withoutMiddleware(ThrottleRequests::class);

        // Without a secret the ZATCA webhook answers 503 "not configured"
        // before its signature check runs, which is a correct response to a
        // misconfiguration and says nothing about the handler.
        config(['zatca-integration.webhook_secret' => 'smoke-test-secret']);

        // Nothing leaves the test. A handler that calls out on its way to
        // failing would otherwise reach a real service from CI.
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        Bus::fake();
        Notification::fake();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['core.test.act']);

        // Everything the catalogue offers, the way the admin role is seeded.
        // Not a super admin: that user has no organization, and the tenant
        // context is most of what these handlers read.
        $this->seed(PermissionsSeeder::class);
        $this->role->permissions()->sync(Permission::pluck('id'));

        foreach (array_keys(config('modules.available', [])) ?: $this->moduleCodes() as $code) {
            OrganizationModule::updateOrCreate(
                ['organization_id' => $this->organization->id, 'module_code' => $code],
                ['is_enabled' => true, 'enabled_at' => now()],
            );
        }

    }

    public function test_no_endpoint_returns_a_server_error(): void
    {
        $this->bootTenant();

        $attempted = [];
        $broken = [];

        foreach ($this->parameterlessGets() as $uri) {
            $attempted[] = 'GET '.$uri;

            $status = $this->getJson('/'.$uri, $this->authHeaders())->baseResponse->getStatusCode();

            if ($status >= 500) {
                $broken[] = 'GET '.$uri;
            }
        }

        sort($broken);

        $this->assertBaseline($broken, $attempted, sprintf(
            'These endpoints answered 5xx. The request reached them and the code '
            ."behind them failed.\n%s",
            implode("\n", $broken)
        ));
    }

    /** @return list<string> */
    private function parameterlessGets(): array
    {
        $out = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v1/') || str_contains($uri, '{')) {
                continue;
            }

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            // Authentication and the customer portal answer to different
            // credentials; they are not this test's subject.
            if (str_starts_with($uri, 'api/v1/auth') || str_starts_with($uri, 'api/v1/portal')) {
                continue;
            }

            $out[] = $uri;
        }

        sort($out);

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    private function moduleCodes(): array
    {
        return [
            'accounting', 'sales', 'purchase', 'inventory', 'manufacturing', 'hr',
            'crm', 'projects', 'maintenance', 'compliance', 'ecommerce', 'real_estate',
            'tm', 'tax', 'trade', 'customs', 'loyalty', 'messaging', 'billing',
            'automation', 'documents', 'calendar', 'taskboard', 'expenses', 'reports',
        ];
    }

    /**
     * The same, for endpoints that take an id.
     *
     * Only where the parameter is a plain string — {id}, {uuid}, {somethingId}.
     * Laravel resolves a type-hinted model before the handler runs, so a bogus
     * value there answers 404 without executing anything, and proves nothing.
     * Where the handler does run, an id that matches no row must come back 404
     * or 422, never a stack trace.
     */
    public function test_no_endpoint_returns_a_server_error_for_an_unknown_id(): void
    {
        $this->bootTenant();

        $attempted = [];
        $broken = [];

        foreach ($this->plainIdGets() as $uri) {
            $attempted[] = 'GET '.$uri;

            $path = preg_replace('/\{\w+\??\}/', '99999999', $uri, 1);
            $status = $this->getJson('/'.$path, $this->authHeaders())->baseResponse->getStatusCode();

            if ($status >= 500) {
                $broken[] = 'GET '.$uri;
            }
        }

        sort($broken);

        $this->assertBaseline($broken, $attempted, sprintf(
            'These endpoints answered 5xx for an id that matches no row.
%s',
            implode('
', $broken)
        ));
    }

    /** @return list<string> */
    private function plainIdGets(): array
    {
        $out = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v1/') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (str_starts_with($uri, 'api/v1/auth') || str_starts_with($uri, 'api/v1/portal')) {
                continue;
            }

            preg_match_all('/\{(\w+)\??\}/', $uri, $m);

            if (count($m[1]) !== 1) {
                continue;
            }

            $param = $m[1][0];

            if ($param === 'id' || $param === 'uuid' || str_ends_with($param, 'Id')) {
                $out[] = $uri;
            }
        }

        sort($out);

        return array_values(array_unique($out));
    }

    /**
     * And the write endpoints, sent nothing.
     *
     * A request with no body is the one every write endpoint has to survive:
     * it either refuses it as invalid, cannot find what it was pointed at, or
     * is not permitted. All three are answers. A stack trace means the
     * request reached code that fell over before deciding anything.
     *
     * This does not check that a write works. It checks that failing to write
     * is handled, which is the half nobody tries by hand.
     */
    public function test_no_write_endpoint_returns_a_server_error(): void
    {
        $this->bootTenant();

        $attempted = [];
        $broken = [];

        foreach ($this->writeEndpoints() as [$verb, $uri]) {
            $attempted[] = $verb.' '.$uri;

            $path = preg_replace('/\{\w+\??\}/', '99999999', $uri);

            $status = $this->json($verb, '/'.$path, [], $this->authHeaders())->baseResponse->getStatusCode();

            if ($status >= 500) {
                $broken[] = $verb.' '.$uri;
            }
        }

        sort($broken);

        $this->assertBaseline($broken, $attempted, sprintf(
            'These write endpoints answered 5xx to a request with no body. '
            .'They failed rather than refusing.
%s',
            implode('
', $broken)
        ));
    }

    /**
     * @return list<array{string, string}>
     */
    private function writeEndpoints(): array
    {
        $out = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/v1/')) {
                continue;
            }

            if (str_starts_with($uri, 'api/v1/auth') || str_starts_with($uri, 'api/v1/portal')) {
                continue;
            }

            preg_match_all('/\{(\w+)\??\}/', $uri, $m);

            // Same restriction as the read pass: a bound model is resolved
            // before the handler runs, so a made-up value proves nothing.
            if ($m[1] !== []) {
                if (count($m[1]) !== 1) {
                    continue;
                }

                $param = $m[1][0];

                if ($param !== 'id' && $param !== 'uuid' && ! str_ends_with($param, 'Id')) {
                    continue;
                }
            }

            foreach ($route->methods() as $verb) {
                if (in_array($verb, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    $out[] = [$verb, $uri];
                }
            }
        }

        sort($out);

        return $out;
    }

    /**
     * The failures found may be in the baseline; the baseline may not grow.
     *
     * Only the entries this pass could have produced are compared, so one pass
     * does not report another's as fixed.
     *
     * @param  list<string>  $found
     */
    /**
     * @param  list<string>  $found  what failed in this pass
     * @param  list<string>  $attempted  every endpoint this pass called
     */
    private function assertBaseline(array $found, array $attempted, string $message): void
    {
        $declared = [];

        foreach (file(self::BASELINE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (! str_starts_with($line, '#')) {
                $declared[] = $line;
            }
        }

        $new = array_values(array_diff($found, $declared));
        sort($new);

        $this->assertSame([], $new, $message);

        // The ones this pass covers that no longer fail. Leaving them declared
        // would let the next regression hide behind an entry already there.
        $fixed = array_values(array_intersect(array_diff($declared, $found), $attempted));
        sort($fixed);

        $this->assertSame([], $fixed, sprintf(
            'These are in %s and no longer fail. Remove them.
%s',
            basename(self::BASELINE),
            implode('
', $fixed)
        ));
    }
}
