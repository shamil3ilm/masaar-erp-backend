<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Controllers\Controller;
use App\Services\Compliance\ZatcaOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    use ReportsBusinessRules;

    public function __construct(
        private readonly ZatcaOnboardingService $onboarding
    ) {}

    /**
     * Get the current ZATCA onboarding status for a branch.
     */
    public function status(string $branchId): JsonResponse
    {
        $branch = $this->onboarding->findBranch($branchId);

        try {
            $result = $this->onboarding->status($branch);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

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
        $branch = $this->onboarding->findBranch($branchId);

        $validated = $request->validate([
            'otp' => ['required', 'string'],
            'csr' => ['required', 'array'],
        ]);

        $result = $this->onboarding->requestCcsid($branch, $validated['otp'], $validated['csr']);

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
        $branch = $this->onboarding->findBranch($branchId);

        try {
            $result = $this->onboarding->runComplianceCheck($branch);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
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
        $branch = $this->onboarding->findBranch($branchId);

        try {
            $result = $this->onboarding->requestPcsid($branch);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success([
            'zatca_branch_id' => $branch->zatca_branch_id,
            'zatca_onboarding_status' => $branch->zatca_onboarding_status,
            'zatca_certificate_expires_at' => $branch->zatca_certificate_expires_at?->toISOString(),
            'compliance_result' => $result->response,
        ]);
    }
}
