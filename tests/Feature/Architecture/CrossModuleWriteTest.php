<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\Core\OrganizationModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A write permission in one module must not buy writes in another.
 *
 * DenyReadOnlyWrites separates a viewer from someone who can act, but it asks
 * only whether the user holds any non-view permission. It cannot tell which
 * module that permission belongs to. Seven modules — real-estate, tm,
 * ecommerce, campaigns, fraud, aml, analytics — reached production readiness
 * with no permission gate at all, so a sales user clearing that single bar
 * could terminate a lease or refund an online payment.
 *
 * These tests hold the gate shut from the outside: they grant a write
 * permission in an unrelated module and assert the request is still refused.
 * Without them the endpoints would look guarded while proving nothing, since
 * the journey tests grant the permission and only ever see the allow path.
 */
class CrossModuleWriteTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('AE');

        // A real permission, in a module that has nothing to do with the
        // endpoints below. This is the escalation being tested.
        $this->setUpAuthenticatedUser(['sales.orders.create']);

        foreach (['real_estate', 'tm', 'ecommerce', 'manufacturing'] as $module) {
            OrganizationModule::updateOrCreate(
                ['organization_id' => $this->organization->id, 'module_code' => $module],
                ['is_enabled' => true, 'enabled_at' => now()],
            );
        }
    }

    /**
     * Endpoints where the permission is the only thing that can refuse.
     *
     * Routes taking a bound model - contracts/{contract}/terminate and the
     * like - answer 404 for an id that does not exist, because binding runs
     * before route middleware. That is the right answer, and it proves
     * nothing about authorization, so the cases below are collection-level.
     *
     * @return list<array{string}>
     */
    public static function writeEndpoints(): array
    {
        return [
            'portfolio creation' => ['/real-estate/portfolios'],
            'contract creation' => ['/real-estate/contracts'],
            'periodic posting' => ['/real-estate/posting-runs/execute'],
            'settlement creation' => ['/real-estate/settlements'],
            'carrier creation' => ['/tm/carriers'],
            'tender creation' => ['/tm/tenders'],
            'rate table creation' => ['/tm/rate-tables'],
            'channel order import' => ['/ecommerce/orders/import'],
            'gateway creation' => ['/ecommerce/payment-gateways'],
            'channel creation' => ['/ecommerce/channels'],
            'campaign creation' => ['/campaigns'],
            'fraud rule creation' => ['/fraud/rules'],
            'suspicious activity report' => ['/aml/sar'],
            'warehouse load' => ['/analytics/warehouse/sync'],
            'engineering change' => ['/manufacturing/engineering-changes'],
            'routing creation' => ['/manufacturing/routings'],
            'corrective action' => ['/manufacturing/capas'],
            'audit plan' => ['/manufacturing/audit-plans'],
            'scrap report' => ['/manufacturing/scrap-reports'],
            'process order' => ['/manufacturing/process/orders'],
            'work permit' => ['/manufacturing/maintenance-permits'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('writeEndpoints')]
    public function test_other_modules_are_refused(string $uri): void
    {
        $response = $this->apiPost($uri, []);

        $this->assertSame(403, $response->status(), sprintf(
            'POST %s answered %d. A user whose only write permission is '
            .'sales.orders.create reached it.',
            $uri, $response->status()
        ));
    }
}
