<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Accounting\CreditHold;
use App\Models\Accounting\CreditLimit;
use App\Models\Sales\Contact;
use App\Services\Accounting\CreditManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditManagementController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly CreditManagementService $creditService
    ) {}

    // -------------------------------------------------------------------------
    // Credit Limits
    // -------------------------------------------------------------------------

    public function indexLimits(Request $request): JsonResponse
    {
        $limits = $this->creditService->listLimits(
            $this->organizationId($request),
            $request->input('risk_class'),
            $request->integer('per_page', 15),
        );

        return $this->paginated($limits, null, 'Credit limits retrieved.');
    }

    public function storeLimit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'         => ['required', $this->ownedBy('contacts')],
            'credit_limit'       => 'required|numeric|min:0',
            'currency_code'      => 'nullable|string|size:3',
            'valid_from'         => 'required|date',
            'valid_until'        => 'nullable|date|after:valid_from',
            'payment_terms_days' => 'nullable|integer|min:0',
            'risk_class'         => 'nullable|in:low,medium,high,blocked',
            'notes'              => 'nullable|string|max:2000',
        ]);

        $contact = $this->creditService->findContact($validated['contact_id']);
        $limit   = $this->creditService->setCreditLimit($contact, $validated);

        return $this->success($limit->load(['contact', 'reviewer']), 'Credit limit saved.', 201);
    }

    public function updateLimit(Request $request, CreditLimit $creditLimit): JsonResponse
    {
        $validated = $request->validate([
            'credit_limit'       => 'sometimes|numeric|min:0',
            'currency_code'      => 'nullable|string|size:3',
            'valid_from'         => 'sometimes|date',
            'valid_until'        => 'nullable|date',
            'payment_terms_days' => 'nullable|integer|min:0',
            'risk_class'         => 'nullable|in:low,medium,high,blocked',
            'notes'              => 'nullable|string|max:2000',
        ]);

        $limit = $this->creditService->setCreditLimit($creditLimit->contact, array_merge(
            $creditLimit->toArray(),
            $validated
        ));

        return $this->success($limit->load(['contact', 'reviewer']), 'Credit limit updated.');
    }

    // -------------------------------------------------------------------------
    // Credit Exposure
    // -------------------------------------------------------------------------

    public function showExposure(Request $request, Contact $contact): JsonResponse
    {
        $exposure = $this->creditService->getCreditExposure($contact);

        return $this->success($exposure, 'Credit exposure retrieved.');
    }

    public function indexExposureSnapshots(Request $request, Contact $contact): JsonResponse
    {
        $snapshots = $this->creditService->listExposureSnapshots(
            $contact,
            $request->integer('per_page', 15),
        );

        return $this->paginated($snapshots, null, 'Credit exposure snapshots retrieved.');
    }

    public function snapshotExposures(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'snapshot_date' => 'nullable|date',
        ]);

        $organization = $this->organization($request);
        $date         = isset($validated['snapshot_date'])
            ? \Illuminate\Support\Carbon::parse($validated['snapshot_date'])
            : now();

        $count = $this->creditService->snapshotExposures($organization, $date);

        return $this->success(['snapshotted' => $count], "Snapshotted {$count} customer exposures.");
    }

    // -------------------------------------------------------------------------
    // Credit Holds
    // -------------------------------------------------------------------------

    public function indexHolds(Request $request): JsonResponse
    {
        $holds = $this->creditService->listHolds(
            $this->organizationId($request),
            $request->boolean('active_only', false),
            $request->integer('per_page', 15),
        );

        return $this->paginated($holds, null, 'Credit holds retrieved.');
    }

    public function placeHold(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate([
            'hold_reason' => 'required|string|max:500',
        ]);

        $hold = $this->creditService->placeHold($contact, $validated);

        return $this->success($hold->load(['contact', 'heldBy']), 'Credit hold placed.', 201);
    }

    public function releaseHold(Request $request, CreditHold $creditHold): JsonResponse
    {
        $validated = $request->validate([
            'release_reason' => 'required|string|max:500',
        ]);

        $hold = $this->creditService->releaseHold($creditHold, $validated['release_reason']);

        return $this->success($hold->load(['contact', 'releasedBy']), 'Credit hold released.');
    }
}
