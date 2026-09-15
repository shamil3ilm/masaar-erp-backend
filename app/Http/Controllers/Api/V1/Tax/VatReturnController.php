<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tax;

use App\Http\Controllers\Controller;
use App\Models\Tax\VatReturnPeriod;
use App\Models\Tax\VatTransaction;
use App\Services\Tax\VatReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VatReturnController extends Controller
{
    public function __construct(
        private readonly VatReturnService $vatReturnService
    ) {}

    /**
     * List VAT return periods for the authenticated organization.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->vatReturnService->paginatePeriods(
            auth()->user()->organization_id,
            $this->filledFilters($request, ['country_code', 'status']),
            $request->integer('per_page', 15),
        ));
    }

    /**
     * Create a new VAT return period.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:3'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after:period_start'],
        ]);

        $period = $this->vatReturnService->preparePeriodFor(
            auth()->user()->organization_id,
            strtoupper($validated['country_code']),
            $validated['period_start'],
            $validated['period_end']
        );

        return $this->success($period, 'VAT return period created', 201);
    }

    /**
     * Show a single VAT return period.
     */
    public function show(VatReturnPeriod $vatReturnPeriod): JsonResponse
    {
        $this->authorizeOrganization($vatReturnPeriod->organization_id);

        $vatReturnPeriod->load('boxes');

        return $this->success($vatReturnPeriod);
    }

    /**
     * Build / rebuild return boxes from transaction data.
     */
    public function buildBoxes(VatReturnPeriod $vatReturnPeriod): JsonResponse
    {
        $this->authorizeOrganization($vatReturnPeriod->organization_id);

        if ($vatReturnPeriod->isSubmitted()) {
            return $this->error('Cannot rebuild boxes for a submitted return.', 'ALREADY_SUBMITTED', 422);
        }

        $period = $this->vatReturnService->buildReturnBoxes($vatReturnPeriod);

        return $this->success($period, 'VAT return boxes built successfully');
    }

    /**
     * Submit a VAT return.
     */
    public function submit(Request $request, VatReturnPeriod $vatReturnPeriod): JsonResponse
    {
        $this->authorizeOrganization($vatReturnPeriod->organization_id);

        if ($vatReturnPeriod->isSubmitted()) {
            return $this->error('This return has already been submitted.', 'ALREADY_SUBMITTED', 422);
        }

        $validated = $request->validate([
            'reference_number' => ['nullable', 'string', 'max:50'],
        ]);

        $period = $this->vatReturnService->submitReturn(
            $vatReturnPeriod,
            $validated['reference_number'] ?? null
        );

        return $this->success($period, 'VAT return submitted successfully');
    }

    /**
     * Export a VAT return summary.
     */
    public function exportReturn(VatReturnPeriod $vatReturnPeriod): JsonResponse
    {
        $this->authorizeOrganization($vatReturnPeriod->organization_id);

        $vatReturnPeriod->load('boxes');

        $reconciliation = $this->vatReturnService->getReconciliationSummary(
            $vatReturnPeriod->organization_id,
            $vatReturnPeriod->country_code,
            $vatReturnPeriod->period_start->toDateString(),
            $vatReturnPeriod->period_end->toDateString()
        );

        return $this->success([
            'period'         => $vatReturnPeriod,
            'boxes'          => $vatReturnPeriod->boxes,
            'reconciliation' => $reconciliation,
        ]);
    }

    /**
     * List VAT transactions for the organization.
     */
    public function indexTransactions(Request $request): JsonResponse
    {
        return $this->paginated($this->vatReturnService->paginateTransactions(
            auth()->user()->organization_id,
            $this->filledFilters($request, ['country_code', 'transaction_type', 'period_start', 'period_end']),
            $request->integer('per_page', 25),
        ));
    }

    /**
     * Record a VAT transaction manually.
     */
    public function storeTransaction(Request $request): JsonResponse
    {
        // A credit note, refund or return reverses a sale, so its amounts are
        // zero or negative; a sale or purchase is zero or positive.
        $sign = in_array($request->input('transaction_type'), VatTransaction::REVERSAL_TYPES, true) ? 'max:0' : 'min:0';

        $validated = $request->validate([
            'transaction_type' => ['required', Rule::in(VatTransaction::TYPES)],
            'source_type'      => ['nullable', 'string', 'max:50'],
            'source_id'        => ['nullable', 'integer'],
            'tax_period'       => ['required', 'date'],
            'taxable_amount'   => ['required', 'numeric', $sign],
            'vat_amount'       => ['required', 'numeric', $sign],
            'vat_rate'         => ['required', 'numeric', 'min:0'],
            'country_code'     => ['required', 'string', 'size:3'],
            'is_exempt'        => ['boolean'],
            'is_zero_rated'    => ['boolean'],
        ]);

        $transaction = $this->vatReturnService->recordTransaction(
            array_merge($validated, ['organization_id' => auth()->user()->organization_id])
        );

        return $this->success($transaction, 'VAT transaction recorded', 201);
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

    /**
     * Ensure the resource belongs to the authenticated user's organization.
     */
    private function authorizeOrganization(int $resourceOrganizationId): void
    {
        if ($resourceOrganizationId !== auth()->user()->organization_id) {
            abort(403, 'Access denied.');
        }
    }
}
