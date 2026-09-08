<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Core\Branch;
use App\Services\Compliance\ComplianceResult;
use App\Services\Compliance\MasaarClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly MasaarClient $client
    ) {}

    /**
     * Get the current ZATCA onboarding status for a branch.
     */
    public function status(string $branchId): JsonResponse
    {
        $branch = Branch::where('uuid', $branchId)->firstOrFail();

        if ($branch->zatca_branch_id === null) {
            return $this->error(
                'Branch has no ZATCA ID',
                'ZATCA_NOT_CONFIGURED',
                400
            );
        }

        $result = $this->client->getOnboardingStatus($branch->zatca_branch_id);

        return $this->success([
            'zatca_branch_id' => $branch->zatca_branch_id,
            'zatca_onboarding_status' => $branch->zatca_onboarding_status,
            'zatca_certificate_expires_at' => $branch->zatca_certificate_expires_at?->toISOString(),
            'compliance_status' => $result->response,
        ]);
    }

    /**
     * Request a Compliance CSID (CCSID) for a branch.
     */
    public function requestCcsid(Request $request, string $branchId): JsonResponse
    {
        $branch = Branch::where('uuid', $branchId)->firstOrFail();

        $validated = $request->validate([
            'otp' => ['required', 'string'],
            'csr' => ['required', 'array'],
        ]);

        $zatcaBranchId = $branch->zatca_branch_id ?? $branchId;

        $result = $this->client->requestCcsid($zatcaBranchId, $validated['otp'], $validated['csr']);

        if ($this->isOnboardingSuccess($result)) {
            $updates = ['zatca_onboarding_status' => 'ccsid_issued'];

            if ($branch->zatca_branch_id === null) {
                $newZatcaId = $result->response['data']['branch_id']
                    ?? $result->response['branch_id']
                    ?? $zatcaBranchId;
                $updates['zatca_branch_id'] = $newZatcaId;
            }

            $branch->update($updates);
            $branch->refresh();
        }

        return $this->success([
            'zatca_branch_id' => $branch->zatca_branch_id,
            'zatca_onboarding_status' => $branch->zatca_onboarding_status,
            'compliance_result' => $result->response,
        ]);
    }

    /**
     * Run the compliance check for a branch.
     */
    public function complianceCheck(string $branchId): JsonResponse
    {
        $branch = Branch::where('uuid', $branchId)->firstOrFail();

        if ($branch->zatca_branch_id === null) {
            return $this->error(
                'Branch has no ZATCA ID',
                'ZATCA_NOT_CONFIGURED',
                400
            );
        }

        $result = $this->client->runComplianceCheck($branch->zatca_branch_id);

        if ($this->isOnboardingSuccess($result)) {
            $branch->update(['zatca_onboarding_status' => 'compliance_checked']);
            $branch->refresh();
        }

        return $this->success([
            'zatca_branch_id' => $branch->zatca_branch_id,
            'zatca_onboarding_status' => $branch->zatca_onboarding_status,
            'compliance_result' => $result->response,
        ]);
    }

    /**
     * Request a Production CSID (PCSID) for a branch.
     */
    public function requestPcsid(string $branchId): JsonResponse
    {
        $branch = Branch::where('uuid', $branchId)->firstOrFail();

        if ($branch->zatca_branch_id === null) {
            return $this->error(
                'Branch has no ZATCA ID',
                'ZATCA_NOT_CONFIGURED',
                400
            );
        }

        $result = $this->client->requestPcsid($branch->zatca_branch_id);

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

        return $this->success([
            'zatca_branch_id' => $branch->zatca_branch_id,
            'zatca_onboarding_status' => $branch->zatca_onboarding_status,
            'zatca_certificate_expires_at' => $branch->zatca_certificate_expires_at?->toISOString(),
            'compliance_result' => $result->response,
        ]);
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
