<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Aml;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Aml\AmlCaseService;
use App\Services\Aml\AmlMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AmlController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly AmlMonitoringService $amlService,
        private readonly AmlCaseService $cases,
    ) {}

    // -------------------------------------------------------------------------
    // Risk Scores
    // -------------------------------------------------------------------------

    /**
     * List contacts by risk level.
     */
    public function riskScores(Request $request): JsonResponse
    {
        $filters = $this->filledFilters($request, ['risk_level']);
        $filters['sanctions_only'] = $request->boolean('sanctions_only');
        $filters['pep_only'] = $request->boolean('pep_only');

        return $this->paginated($this->cases->paginateRiskScores(
            Auth::user()->organization_id,
            $filters,
            $request->integer('per_page', 20),
        ));
    }

    /**
     * Get full risk breakdown for a single contact.
     */
    public function contactRisk(int $contactId): JsonResponse
    {
        return $this->success($this->cases->contactRisk(Auth::user()->organization_id, $contactId));
    }

    // -------------------------------------------------------------------------
    // Transaction Flags
    // -------------------------------------------------------------------------

    /**
     * Paginated list of flagged transactions.
     */
    public function transactionFlags(Request $request): JsonResponse
    {
        $filters = $this->filledFilters($request, ['status', 'flag_reason', 'transaction_type']);

        if ($request->filled('contact_id')) {
            $filters['contact_id'] = $request->integer('contact_id');
        }

        return $this->paginated($this->cases->paginateTransactionFlags(
            Auth::user()->organization_id,
            $filters,
            $request->integer('per_page', 20),
        ));
    }

    // -------------------------------------------------------------------------
    // Suspicious Activity Reports
    // -------------------------------------------------------------------------

    /**
     * List SAR records.
     */
    public function suspiciousActivities(Request $request): JsonResponse
    {
        return $this->paginated($this->cases->paginateSuspiciousActivities(
            Auth::user()->organization_id,
            $this->filledFilters($request, ['status', 'report_type', 'activity_type']),
            $request->integer('per_page', 20),
        ));
    }

    /**
     * Manually create a SAR.
     */
    public function createSar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'      => ['required', 'integer', $this->ownedBy('contacts')],
            'activity_type'   => 'required|in:structuring,smurfing,layering,unusual_pattern,sanctions_hit',
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'integer',
            'description'     => 'required|string|max:5000',
            'report_type'     => 'nullable|in:SAR,CTR,STR',
        ]);

        $sar = $this->amlService->createSar(
            organizationId: Auth::user()->organization_id,
            contactId:      $validated['contact_id'],
            activityType:   $validated['activity_type'],
            transactionIds: $validated['transaction_ids'],
            description:    $validated['description'],
            createdBy:      Auth::id(),
            reportType:     $validated['report_type'] ?? null,
        );

        return $this->created($this->cases->presentSuspiciousActivity($sar), 'SAR created successfully.');
    }

    // -------------------------------------------------------------------------
    // Contact Screening
    // -------------------------------------------------------------------------

    /**
     * Trigger an immediate re-screening of a contact.
     */
    public function screenContact(int $contactId): JsonResponse
    {
        $contact = $this->cases->findContact(Auth::user()->organization_id, $contactId);

        try {
            $this->cases->dispatchScreening($contact);
        } catch (\Throwable $e) {
            // The cause can name queue hosts or credentials; it goes to the log only.
            Log::error('AML screening dispatch failed', ['contact_id' => $contact->id, 'error' => $e->getMessage()]);

            return $this->error('Failed to dispatch screening job.', 'DISPATCH_FAILED', 500);
        }

        return $this->success(null, 'AML screening dispatched for contact.');
    }

    /**
     * The given query parameters the request fills, by name.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function filledFilters(Request $request, array $keys): array
    {
        $filters = [];

        foreach ($keys as $key) {
            if ($request->filled($key)) {
                $filters[$key] = $request->input($key);
            }
        }

        return $filters;
    }
}
