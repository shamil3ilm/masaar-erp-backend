<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\Core\OrganizationModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The permission a route names must be one that can actually be granted.
 *
 * CrossModuleWriteTest proves these endpoints refuse the wrong user. On its
 * own that is satisfied by an endpoint which refuses everyone — a permission
 * nobody holds, or a slug with a typo, denies just as convincingly as a
 * correct gate. Most of the routes gated here have no feature test at all, so
 * nothing else would notice.
 *
 * So each case grants exactly the permission the route names and asserts the
 * request is not refused. What happens after authorization is not this test's
 * business: 422 from validation is a pass, because the request reached the
 * controller.
 */
class PermissionOpensGateTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    /**
     * @return list<array{string, string}>
     */
    public static function gatedEndpoints(): array
    {
        return [
            'onboarding' => ['hr.lifecycle.manage', '/hr/onboarding'],
            'exit management' => ['hr.lifecycle.manage', '/hr/exit-management'],
            'travel request' => ['hr.travel.manage', '/hr/travel/requests'],
            'position' => ['hr.org.manage', '/hr/positions'],
            'compensation' => ['hr.compensation.manage', '/hr/compensation'],
            'time sheet' => ['hr.attendance.manage', '/hr/time-sheets'],
            'off-cycle payroll' => ['hr.payroll.process', '/hr/off-cycle-payroll'],
            'manager delegation' => ['hr.delegations.manage', '/hr/manager/delegations'],
            'leave policy' => ['hr.leave.manage', '/hr/leave-management/policies'],
            'engineering change' => ['manufacturing.planning.manage', '/manufacturing/engineering-changes'],
            'scrap report' => ['manufacturing.production.manage', '/manufacturing/scrap-reports'],
            'work permit' => ['maintenance.permits.manage', '/maintenance/permits'],
            'audit plan' => ['manufacturing.quality.manage', '/manufacturing/audit-plans'],
            'rental contract' => ['real_estate.contracts.manage', '/real-estate/contracts'],
            'carrier' => ['tm.carriers.manage', '/tm/carriers'],
            'sales channel' => ['ecommerce.channels.manage', '/ecommerce/channels'],
        ];
    }

    #[DataProvider('gatedEndpoints')]
    public function test_the_named_permission_admits(string $permission, string $uri): void
    {
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([$permission]);

        foreach (['real_estate', 'tm', 'ecommerce', 'manufacturing', 'maintenance'] as $module) {
            OrganizationModule::updateOrCreate(
                ['organization_id' => $this->organization->id, 'module_code' => $module],
                ['is_enabled' => true, 'enabled_at' => now()],
            );
        }

        $status = $this->apiPost($uri, [])->status();

        $this->assertNotSame(403, $status, sprintf(
            'POST %s refused a user holding %s, the permission the route itself names.',
            $uri, $permission
        ));

        $this->assertNotSame(404, $status, sprintf(
            'POST %s is not routed. The gate cannot be the reason, so the path is wrong.',
            $uri
        ));
    }
}
