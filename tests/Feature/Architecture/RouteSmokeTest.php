<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\Core\OrganizationModule;
use App\Models\Core\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * No endpoint may answer 500 to a request that reaches it.
 *
 * 21% of the routes in this API are exercised by a test, so most of the
 * surface has never been called by anything. That is how DpsScreeningRun sat
 * unloadable: it redeclared an Eloquent method with a narrower signature, PHP
 * refused the class, and denied-party screening was fatal on every request —
 * for as long as nobody looked.
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

    /** Endpoints that answer 5xx for a reason that is not a defect. */
    private const ACCEPTED = [];

    /** Endpoints answering 5xx for an unknown id, for a reason that is not a defect. */
    private const ACCEPTED_BY_ID = [];

    private function bootTenant(): void
    {
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['core.test.act']);

        // Everything the catalogue offers, the way the admin role is seeded.
        // Not a super admin: that user has no organization, and the tenant
        // context is most of what these handlers read.
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
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

        $broken = [];

        foreach ($this->parameterlessGets() as $uri) {
            $status = $this->getJson('/'.$uri, $this->authHeaders())->status();

            if ($status >= 500) {
                $broken[] = $status.' '.$uri;
            }
        }

        sort($broken);

        $this->assertSame(self::ACCEPTED, $broken, sprintf(
            "These endpoints answered 5xx. The request reached them and the code "
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

        $broken = [];

        foreach ($this->plainIdGets() as $uri) {
            $path = preg_replace('/\{\w+\??\}/', '99999999', $uri, 1);
            $status = $this->getJson('/'.$path, $this->authHeaders())->status();

            if ($status >= 500) {
                $broken[] = $status.' '.$uri;
            }
        }

        sort($broken);

        $this->assertSame(self::ACCEPTED_BY_ID, $broken, sprintf(
            "These endpoints answered 5xx for an id that matches no row.
%s",
            implode("
", $broken)
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
}
