<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\AdvancePayment;
use App\Services\Sales\CustomerAdvanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerAdvanceController extends Controller
{
    public function __construct(
        private CustomerAdvanceService $advanceService,
    ) {}

    /**
     * GET /sales/customer-advances
     */
    public function index(Request $request): JsonResponse
    {
        $filters = array_merge(
            $request->only(['contact_id', 'status', 'from_date', 'to_date', 'per_page']),
            ['organization_id' => $request->user()->organization_id],
        );

        return $this->paginated($this->advanceService->index($filters));
    }

    /**
     * POST /sales/customer-advances
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $validated = $request->validate([
            'contact_id' => ['required', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['required', 'string', 'size:3'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('organization_id', $orgId)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $advance = $this->advanceService->store(array_merge($validated, [
            'organization_id' => $orgId,
            'received_by' => $request->user()->id,
        ]));

        return $this->created($advance->load(['contact:id,contact_name,company_name', 'applications']));
    }

    /**
     * GET /sales/customer-advances/{advancePayment}
     */
    public function show(AdvancePayment $advancePayment): JsonResponse
    {
        return $this->success($this->advanceService->loadDetails($advancePayment));
    }

    /**
     * DELETE /sales/customer-advances/{advancePayment}
     */
    public function destroy(AdvancePayment $advancePayment): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->advanceService->delete($advancePayment),
            'Advance payment deleted.',
            'INVALID_STATUS'
        );
    }

    /**
     * POST /sales/customer-advances/{advancePayment}/apply
     */
    public function applyToInvoice(Request $request, AdvancePayment $advancePayment): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', Rule::exists('invoices', 'id')->where('organization_id', $request->user()->organization_id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $application = $this->advanceService->applyToInvoiceId(
            $advancePayment,
            (int) $validated['invoice_id'],
            (float) $validated['amount'],
            $request->user()->id,
        );

        return $this->created($application);
    }

    /**
     * GET /sales/customer-advances/contact/{contactId}/open
     */
    public function openAdvances(Request $request, int $contactId): JsonResponse
    {
        $advances = $this->advanceService->getOpenAdvancesForContact(
            $contactId,
            $request->user()->organization_id,
        );

        return $this->success($advances);
    }

    /**
     * POST /sales/customer-advances/{advancePayment}/refund
     */
    public function refund(AdvancePayment $advancePayment): JsonResponse
    {
        $this->advanceService->refund($advancePayment);

        return $this->success($advancePayment->fresh(), 'Advance refunded successfully.');
    }
}
