<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Core\Branch;

/**
 * Onboards a branch to ZATCA through the compliance platform and records each
 * step the platform confirms on the branch.
 *
 * The platform is called before the branch is written, never inside a
 * transaction, and a step is recorded only when the platform reports it
 * passed.
 */
final class ZatcaOnboardingService
{
    public function __construct(
        private readonly MasaarClient $client,
    ) {}

    /**
     * The organization's branch with this uuid.
     */
    public function findBranch(string $uuid): Branch
    {
        return Branch::where('uuid', $uuid)->firstOrFail();
    }

    /**
     * What the platform reports about the branch's onboarding.
     *
     * @throws BusinessRuleException when the branch has no ZATCA id
     */
    public function status(Branch $branch): ComplianceResult
    {
        return $this->client->getOnboardingStatus($this->zatcaIdOf($branch));
    }

    /**
     * Requests a compliance CSID. A branch without a ZATCA id is sent under
     * its own uuid and adopts the id the platform issues.
     */
    public function requestCcsid(Branch $branch, string $otp, array $csr): ComplianceResult
    {
        $zatcaBranchId = $branch->zatca_branch_id ?? $branch->uuid;

        $result = $this->client->requestCcsid($zatcaBranchId, $otp, $csr);

        if ($this->isOnboardingSuccess($result)) {
            $updates = ['zatca_onboarding_status' => 'ccsid_issued'];

            if ($branch->zatca_branch_id === null) {
                $updates['zatca_branch_id'] = $result->response['data']['branch_id']
                    ?? $result->response['branch_id']
                    ?? $zatcaBranchId;
            }

            $branch->update($updates);
            $branch->refresh();
        }

        return $result;
    }

    /**
     * Runs the platform's compliance check of the branch's test invoices.
     *
     * @throws BusinessRuleException when the branch has no ZATCA id
     */
    public function runComplianceCheck(Branch $branch): ComplianceResult
    {
        $result = $this->client->runComplianceCheck($this->zatcaIdOf($branch));

        if ($this->isOnboardingSuccess($result)) {
            $branch->update(['zatca_onboarding_status' => 'compliance_checked']);
            $branch->refresh();
        }

        return $result;
    }

    /**
     * Requests the production CSID and records its expiry.
     *
     * @throws BusinessRuleException when the branch has no ZATCA id
     */
    public function requestPcsid(Branch $branch): ComplianceResult
    {
        $result = $this->client->requestPcsid($this->zatcaIdOf($branch));

        if ($this->isOnboardingSuccess($result)) {
            $updates = ['zatca_onboarding_status' => 'pcsid_issued'];

            $expiresAt = $result->response['data']['expires_at']
                ?? $result->response['expires_at']
                ?? null;

            if ($expiresAt !== null) {
                $updates['zatca_certificate_expires_at'] = $expiresAt;
            }

            $branch->update($updates);
            $branch->refresh();
        }

        return $result;
    }

    private function zatcaIdOf(Branch $branch): string
    {
        if ($branch->zatca_branch_id === null) {
            throw new BusinessRuleException('Branch has no ZATCA ID', 'ZATCA_NOT_CONFIGURED', 400);
        }

        return $branch->zatca_branch_id;
    }

    /**
     * Whether an onboarding call actually advanced the branch.
     *
     * Absence of an error is not success. The compliance check answers 200
     * with passed false when ZATCA refuses one of its six test invoices, and
     * recording that as a completed step would leave a branch believing it is
     * onboarded when it is not.
     */
    private function isOnboardingSuccess(ComplianceResult $result): bool
    {
        if (in_array($result->status, ['error', 'not_applicable', 'rejected', 'failed'], true)) {
            return false;
        }

        $passed = $result->response['data']['passed'] ?? $result->response['passed'] ?? null;

        return $passed !== false;
    }
}
