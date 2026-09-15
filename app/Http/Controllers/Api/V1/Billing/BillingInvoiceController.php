<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Controllers\Controller;
use App\Models\Billing\BillingInvoice;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingInvoiceController extends Controller
{
    use ReportsBusinessRules;

    public function __construct(private readonly BillingService $billingService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->billingService->paginateInvoices(
            auth()->user()->organization_id,
            (int) $request->input('per_page', 20),
        ));
    }

    public function show(BillingInvoice $invoice): JsonResponse
    {
        return $this->success($invoice->load('items', 'payments'));
    }

    /**
     * Records an invoice as paid in full. The route admits platform
     * administrators only: a tenant settles its invoices through payment, not
     * by marking them.
     */
    public function pay(BillingInvoice $invoice): JsonResponse
    {
        try {
            return $this->success($this->billingService->markInvoicePaid($invoice));
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }
    }
}
