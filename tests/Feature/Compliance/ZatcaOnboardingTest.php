<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Models\Core\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Onboarding a branch to ZATCA, from this side of the seam.
 *
 * Four endpoints the staff app calls and the reason this ERP exists. What is
 * checked here is the ERP's half: that a branch without a ZATCA id is refused
 * rather than sent onward, that the compliance platform's answer is recorded
 * on the branch, and that a refusal is not recorded as a success. The
 * certificate work itself lives in the compliance platform and is tested
 * there against ZATCA's own sandbox.
 */
class ZatcaOnboardingTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Branch $zatcaBranch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'compliance.onboarding.view',
            'compliance.onboarding.manage',
        ]);

        config(['zatca-integration.enabled' => true]);
        config(['zatca-integration.url' => 'https://compliance.test']);
        config(['zatca-integration.retry.times' => 1]);

        Http::preventStrayRequests();

        $this->zatcaBranch = Branch::factory()->create([
            'organization_id' => $this->organization->id,
            'zatca_branch_id' => 'ZB-1',
            'zatca_onboarding_status' => 'pending',
        ]);
    }

    private function url(Branch $branch, string $step): string
    {
        return '/compliance/branches/'.$branch->uuid.'/onboarding/'.$step;
    }

    public function test_status_refuses_a_branch_without_zatca_id(): void
    {
        Http::fake();

        $branch = Branch::factory()->create([
            'organization_id' => $this->organization->id,
            'zatca_branch_id' => null,
        ]);

        $this->apiGet($this->url($branch, 'status'))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'ZATCA_NOT_CONFIGURED');

        Http::assertNothingSent();
    }

    public function test_status_reports_what_the_platform_says(): void
    {
        Http::fake([
            '*/onboarding/status*' => Http::response(['status' => 'pcsid_issued'], 200),
        ]);

        $this->apiGet($this->url($this->zatcaBranch, 'status'))
            ->assertOk()
            ->assertJsonPath('data.zatca_branch_id', 'ZB-1')
            ->assertJsonPath('data.compliance_status.status', 'pcsid_issued');
    }

    public function test_ccsid_requires_an_otp_and_csr(): void
    {
        Http::fake();

        $this->apiPost($this->url($this->zatcaBranch, 'ccsid'), [])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_ccsid_records_the_issued_state(): void
    {
        Http::fake([
            '*/onboarding/ccsid' => Http::response(['status' => 'ccsid_issued'], 200),
        ]);

        $this->apiPost($this->url($this->zatcaBranch, 'ccsid'), [
            'otp' => '123345',
            'csr' => ['common_name' => 'TST-Branch'],
        ])->assertOk();

        $this->assertSame('ccsid_issued', $this->zatcaBranch->fresh()->zatca_onboarding_status);
    }

    public function test_ccsid_adopts_the_id_it_is_given(): void
    {
        Http::fake([
            '*/onboarding/ccsid' => Http::response([
                'status' => 'ccsid_issued',
                'data' => ['branch_id' => 'ZB-NEW'],
            ], 200),
        ]);

        $branch = Branch::factory()->create([
            'organization_id' => $this->organization->id,
            'zatca_branch_id' => null,
        ]);

        $this->apiPost($this->url($branch, 'ccsid'), [
            'otp' => '123345',
            'csr' => ['common_name' => 'TST-Branch'],
        ])->assertOk();

        $this->assertSame('ZB-NEW', $branch->fresh()->zatca_branch_id);
    }

    public function test_compliance_check_records_the_pass(): void
    {
        Http::fake([
            '*/onboarding/compliance-check' => Http::response(['status' => 'passed'], 200),
        ]);

        $this->apiPost($this->url($this->zatcaBranch, 'compliance-check'))->assertOk();

        $this->assertSame('compliance_checked', $this->zatcaBranch->fresh()->zatca_onboarding_status);
    }

    public function test_a_failed_compliance_check_is_not_recorded(): void
    {
        // The platform answers 200 with passed false when ZATCA refuses one of
        // the six test invoices. That is a refusal, not a completed step.
        Http::fake([
            '*/onboarding/compliance-check' => Http::response([
                'passed' => false,
                'results' => [['invoice' => 1, 'passed' => false]],
            ], 200),
        ]);

        $this->apiPost($this->url($this->zatcaBranch, 'compliance-check'))->assertOk();

        $this->assertSame('pending', $this->zatcaBranch->fresh()->zatca_onboarding_status);
    }

    public function test_pcsid_stores_the_certificate_expiry(): void
    {
        Http::fake([
            '*/onboarding/pcsid' => Http::response([
                'status' => 'pcsid_issued',
                'data' => ['expires_at' => '2030-01-31T00:00:00Z'],
            ], 200),
        ]);

        $this->apiPost($this->url($this->zatcaBranch, 'pcsid'))->assertOk();

        $branch = $this->zatcaBranch->fresh();

        $this->assertSame('pcsid_issued', $branch->zatca_onboarding_status);
        $this->assertSame('2030-01-31', $branch->zatca_certificate_expires_at->toDateString());
    }

    public function test_a_rejected_request_is_not_recorded_as_issued(): void
    {
        Http::fake([
            '*/onboarding/ccsid' => Http::response([
                'status' => 'rejected',
                'message' => 'ZATCA refused the CSR',
            ], 200),
        ]);

        $this->apiPost($this->url($this->zatcaBranch, 'ccsid'), [
            'otp' => '000000',
            'csr' => ['common_name' => 'TST-Branch'],
        ])->assertOk();

        $this->assertSame('pending', $this->zatcaBranch->fresh()->zatca_onboarding_status);
    }

    public function test_an_error_leaves_the_branch_alone(): void
    {
        Http::fake([
            '*/onboarding/pcsid' => Http::response(['message' => 'gateway down'], 500),
        ]);

        $this->apiPost($this->url($this->zatcaBranch, 'pcsid'))->assertOk();

        $this->assertSame('pending', $this->zatcaBranch->fresh()->zatca_onboarding_status);
    }

    public function test_another_organizations_branch_is_unreachable(): void
    {
        Http::fake();

        $other = Branch::factory()->create(['zatca_branch_id' => 'ZB-OTHER']);

        $this->apiGet($this->url($other, 'status'))->assertStatus(404);

        Http::assertNothingSent();
    }
}
