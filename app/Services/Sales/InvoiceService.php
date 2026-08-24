<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Core\Organization;
use App\Models\Core\UserEvent;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCodes;
use App\Models\Inventory\Product;
use App\Orchestrators\Sales\PostInvoiceOrchestrator;
use App\Services\Accounting\CreditManagementService;
use App\Services\Accounting\JournalEntryFactory;
use App\Services\Accounting\JournalService;
use App\Services\Compliance\CompliPayClient;
use App\Services\Core\NumberGeneratorService;
use App\Services\Core\UserEventService;
use App\Services\Inventory\StockService;
use App\Services\Sales\RebateAccrualService;
use App\Services\Tax\TaxCalculatorService;
use App\Jobs\RetryComplianceSubmission;
use App\Jobs\RunFraudChecksJob;
use Illuminate\Http\Client\ConnectionException;
use App\Models\Concerns\ChecksIdempotency;
use App\Traits\StructuredLogger;
use Illuminate\Support\Facades\DB;

/**
 * Manages the full lifecycle of sales invoices within a multi-tenant ERP.
 *
 * Responsibilities:
 * - Create, update, and delete draft invoices with tax calculation and line management
 * - Transition invoices through states: draft → sent → partial/overdue → paid → voided
 * - Generate and post double-entry journal entries (AR debit, revenue/tax credit) on send
 * - Enforce credit limits and credit holds at both creation and confirmation time
 * - Perform ATP (Available-to-Promise) stock checks before persisting invoice lines
 * - Submit invoices to ZATCA for e-invoicing compliance (Saudi/GCC)
 * - Convert quotations and sales orders into invoices
 * - Create credit notes against existing invoices
 *
 * Side Effects:
 * - Writes Journal entries to the accounting ledger via JournalService on send
 * - Deducts stock levels via StockService on send; returns stock on void
 * - Dispatches GenerateInvoiceDocumentJob (PDF + email) after send commits
 * - Dispatches RunFraudChecksJob asynchronously after create commits
 * - Creates CreditHold records when a credit limit is breached
 * - Tracks user events via UserEventService for INVOICE_CREATED and INVOICE_SENT
 * - Triggers rebate accrual via RebateAccrualService on send (non-blocking)
 * - Schedules RetryComplianceSubmission job on ZATCA connection failure
 *
 * Idempotency:
 * - create() is NOT idempotent; duplicate calls produce duplicate invoices
 * - send() is guarded by pessimistic lock (lockForUpdate) to prevent double-posting
 * - void() is idempotent if status is already STATUS_VOIDED (throws on second call)
 *
 * CONTRACT:
 * - Callers must supply validated line data (positive quantity, non-negative unit_price)
 * - send() must NOT be called concurrently on the same invoice from multiple requests
 * - createJournalEntry() and deductInventory() must be called inside a DB transaction
 * - Chart of accounts (receivable, sales, tax_payable) must be configured in erp config
 *   before invoices can be sent; missing accounts will cause a runtime error
 */
class InvoiceService
{
    use ChecksIdempotency, StructuredLogger;
    public function __construct(
        private TaxCalculatorService $taxCalculator,
        private JournalService $journalService,
        private JournalEntryFactory $journalEntryFactory,
        private StockService $stockService,
        private NumberGeneratorService $numberGenerator,
        private CompliPayClient $compliPayClient,
        private UserEventService $userEventService,
        private CreditManagementService $creditManagementService,
        private RebateAccrualService $rebateAccrualService,
        private PostInvoiceOrchestrator $postInvoiceOrchestrator,
    ) {}

    /**
     * Create a new invoice.
     */
    public function create(array $data, array $lines): Invoice
    {
        $invoice = DB::transaction(function () use ($data, $lines) {
            $organization = Organization::findOrFail(auth()->user()->organization_id);
            $customer = Contact::where('organization_id', auth()->user()->organization_id)
                ->findOrFail($data['customer_id']);

            // Generate invoice number
            if (empty($data['invoice_number'])) {
                $prefix = ($data['invoice_type'] ?? 'standard') === Invoice::TYPE_CREDIT_NOTE ? 'CN' : 'INV';
                $data['invoice_number'] = $this->numberGenerator->generate($prefix);
            }

            // Set customer details
            $data['customer_name'] = $customer->getDisplayName();
            $data['customer_email'] = $customer->email;
            $data['customer_tax_number'] = $customer->tax_number;
            $data['billing_address'] = $data['billing_address'] ?? $customer->getBillingAddress();
            $data['shipping_address'] = $data['shipping_address'] ?? $customer->getShippingAddress();

            // Set defaults
            $data['currency_code'] = $data['currency_code'] ?? $customer->currency_code ?? $organization->base_currency;
            $data['exchange_rate'] = $data['exchange_rate'] ?? 1;
            $data['due_date'] = $data['due_date'] ?? now()->addDays($customer->payment_terms);

            // --- Credit limit check (SAP-style AR enforcement) ---
            // Acquire a row-level lock on the credit limit record before reading exposure
            // so that concurrent invoice creation requests are serialised at the DB level.
            // This prevents two simultaneous requests from both passing the credit check
            // and together exceeding the limit. The lock is released when this transaction
            // commits or rolls back.
            $lockedCreditLimit = \App\Models\Accounting\CreditLimit::where('organization_id', $customer->organization_id)
                ->where('contact_id', $customer->id)
                ->lockForUpdate()
                ->first();

            // Calculate the gross line total so we can check exposure before persisting.
            $lineTotal = '0';
            foreach ($lines as $line) {
                $lineTotal = bcadd($lineTotal, bcmul((string) ($line['unit_price'] ?? 0), (string) ($line['quantity'] ?? 0), 4), 4);
            }
            // Apply any header-level discount
            $discountValue = (string) ($data['discount_value'] ?? 0);
            $discountType  = $data['discount_type'] ?? 'fixed';
            if ($discountType === 'percentage') {
                $discountAmount = bcdiv(bcmul($lineTotal, $discountValue, 4), '100', 4);
                $invoiceTotal   = bcsub($lineTotal, $discountAmount, 4);
            } else {
                $invoiceTotal = bcsub($lineTotal, $discountValue, 4);
            }
            if (bccomp($invoiceTotal, '0', 4) < 0) {
                $invoiceTotal = '0';
            }
            if (!$this->creditManagementService->checkCreditLimit($customer, $invoiceTotal)) {
                throw ApiException::fromError(
                    ErrorCodes::SALES_INSUFFICIENT_CREDIT,
                    ['customer_id' => $customer->id, 'requested_amount' => $invoiceTotal],
                    "Customer '{$customer->getDisplayName()}' has exceeded their credit limit. Please review the credit limit or obtain approval before proceeding."
                );
            }

            // --- ATP (Available-to-Promise) check per line ---
            // Skipped for credit notes: credit notes return inventory, not consume it.
            $isCreditNote = ($data['invoice_type'] ?? '') === Invoice::TYPE_CREDIT_NOTE;

            if (!$isCreditNote) {
                $insufficientLines = [];
                foreach ($lines as $lineData) {
                    $productId  = $lineData['product_id'] ?? null;
                    $warehouseId = $lineData['warehouse_id'] ?? null;
                    $quantity   = (float) ($lineData['quantity'] ?? 0);
                    $variantId  = $lineData['variant_id'] ?? null;

                    if (!$productId || !$warehouseId || $quantity <= 0) {
                        continue;
                    }

                    $product = Product::where('organization_id', $invoice->organization_id ?? auth()->user()->organization_id)
                        ->find($productId);
                    if (!$product || !$product->track_inventory) {
                        continue;
                    }

                    if (!$this->stockService->hasAvailableStock($productId, $warehouseId, $quantity, $variantId)) {
                        $available = $this->stockService->getStockLevel($productId, $warehouseId, $variantId);
                        $insufficientLines[] = [
                            'product_id'  => $productId,
                            'product_name' => $product->name,
                            'warehouse_id' => $warehouseId,
                            'requested'   => $quantity,
                            'available'   => $available ? (float) $available->quantity - (float) $available->reserved_quantity : 0.0,
                        ];
                    }
                }

                if (!empty($insufficientLines)) {
                    throw ApiException::fromError(
                        ErrorCodes::INV_INSUFFICIENT_STOCK,
                        ['insufficient_lines' => $insufficientLines],
                        'One or more line items have insufficient stock available to fulfill this invoice.'
                    );
                }
            }

            // Determine compliance requirement
            $data['compliance_status'] = $this->determineComplianceStatus($organization);

            // Set default status
            $data['status'] = $data['status'] ?? Invoice::STATUS_DRAFT;

            $invoice = Invoice::create($data);

            // Calculate taxes and create lines
            $taxResult = $this->taxCalculator->calculate(
                $organization,
                $lines,
                $data['place_of_supply'] ?? null
            );

            foreach ($lines as $index => $lineData) {
                if ((float) ($lineData['quantity'] ?? 0) <= 0 || (float) ($lineData['unit_price'] ?? 0) < 0) {
                    throw new \InvalidArgumentException('Invoice line quantity must be positive and unit price cannot be negative.');
                }

                $taxLine = $taxResult->lines[$index] ?? [];

                $invoice->lines()->create(array_merge($lineData, [
                    'tax_rate' => $taxLine['tax_rate'] ?? 0,
                    'tax_amount' => $taxLine['tax_amount'] ?? 0,
                    'tax_code' => $taxLine['tax_code'] ?? 'S',
                    'cgst_rate' => $taxLine['cgst_rate'] ?? 0,
                    'cgst_amount' => $taxLine['cgst_amount'] ?? 0,
                    'sgst_rate' => $taxLine['sgst_rate'] ?? 0,
                    'sgst_amount' => $taxLine['sgst_amount'] ?? 0,
                    'igst_rate' => $taxLine['igst_rate'] ?? 0,
                    'igst_amount' => $taxLine['igst_amount'] ?? 0,
                    'line_order' => $index,
                ]));
            }

            $invoice->recalculateTotals();

            return $invoice->load('lines', 'customer');
        });

        try {
            $this->userEventService->track(
                UserEvent::INVOICE_CREATED,
                ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'total' => $invoice->total],
                auth('api')->id(),
                $invoice->organization_id,
            );
        } catch (\Throwable $e) {
            $this->logWarning('Event tracking failed', ['event' => UserEvent::INVOICE_CREATED, 'error' => $e->getMessage()]);
        }

        // Dispatch fraud check asynchronously — non-blocking.
        // afterCommit() ensures the job is only queued once the outer transaction
        // (e.g. createFromSalesOrder) has fully committed, preventing phantom jobs
        // on rollback.
        try {
            RunFraudChecksJob::dispatch(
                'invoice',
                $invoice->id,
                [
                    'uuid'       => $invoice->uuid,
                    'total'      => (float) $invoice->total,
                    'contact_id' => $invoice->customer_id,
                    'currency'   => $invoice->currency_code,
                ],
                $invoice->organization_id,
                auth('api')->id(),
            )->afterCommit();
        } catch (\Throwable $e) {
            $this->logWarning('Fraud check dispatch failed for invoice', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
        }

        return $invoice;
    }

    /**
     * Update a draft invoice.
     */
    public function update(Invoice $invoice, array $data, ?array $lines = null): Invoice
    {
        if (!$invoice->isEditable()) {
            throw new \InvalidArgumentException('Only draft invoices can be updated.');
        }

        return DB::transaction(function () use ($invoice, $data, $lines) {
            // Optimistic locking check
            if (isset($data['version']) && $data['version'] !== $invoice->version) {
                throw new \App\Exceptions\ConcurrencyException(
                    'Invoice has been modified by another user.',
                    $invoice
                );
            }

            $invoice->update(array_merge($data, ['version' => $invoice->version + 1]));

            if ($lines !== null) {
                $organization = Organization::findOrFail($invoice->organization_id);
                $invoice->lines()->delete();

                $taxResult = $this->taxCalculator->calculate(
                    $organization,
                    $lines,
                    $invoice->place_of_supply
                );

                foreach ($lines as $index => $lineData) {
                    if ((float) ($lineData['quantity'] ?? 0) <= 0 || (float) ($lineData['unit_price'] ?? 0) < 0) {
                        throw new \InvalidArgumentException('Invoice line quantity must be positive and unit price cannot be negative.');
                    }

                    $taxLine = $taxResult->lines[$index] ?? [];

                    $invoice->lines()->create(array_merge($lineData, [
                        'tax_rate' => $taxLine['tax_rate'] ?? 0,
                        'tax_amount' => $taxLine['tax_amount'] ?? 0,
                        'tax_code' => $taxLine['tax_code'] ?? 'S',
                        'cgst_rate' => $taxLine['cgst_rate'] ?? 0,
                        'cgst_amount' => $taxLine['cgst_amount'] ?? 0,
                        'sgst_rate' => $taxLine['sgst_rate'] ?? 0,
                        'sgst_amount' => $taxLine['sgst_amount'] ?? 0,
                        'igst_rate' => $taxLine['igst_rate'] ?? 0,
                        'igst_amount' => $taxLine['igst_amount'] ?? 0,
                        'line_order' => $index,
                    ]));
                }

                $invoice->recalculateTotals();
            }

            return $invoice->fresh(['lines', 'customer']);
        });
    }

    /**
     * Enforce credit limit and active credit hold rules at the point of confirmation.
     *
     * Called inside a DB::transaction with a row-level lock already held on the
     * invoice row, so concurrent confirmations are serialised at the DB level.
     *
     * @throws \InvalidArgumentException when the customer is on active credit hold.
     * @throws ApiException              when the new invoice would breach the limit.
     */
    public function checkCreditLimit(\App\Models\Sales\Contact $contact, float $newAmount): void
    {
        // 1. Active credit-hold check — always blocks, regardless of limit.
        $activeHold = \App\Models\Accounting\CreditHold::where('organization_id', $contact->organization_id)
            ->where('contact_id', $contact->id)
            ->active()
            ->first();

        if ($activeHold) {
            throw new \InvalidArgumentException(
                "Customer is on credit hold: {$activeHold->hold_reason}"
            );
        }

        // 2. Credit limit check — only enforced when a limit record exists.
        $creditLimit = \App\Models\Accounting\CreditLimit::where('organization_id', $contact->organization_id)
            ->where('contact_id', $contact->id)
            ->active()
            ->lockForUpdate()
            ->first();

        if (!$creditLimit) {
            // No limit configured — allow unconditionally.
            return;
        }

        // Blocked risk class is treated as a hard stop even without a hold record.
        if ($creditLimit->isBlocked()) {
            // Create an automated hold so the block is visible in the UI.
            \App\Models\Accounting\CreditHold::firstOrCreate(
                [
                    'organization_id' => $contact->organization_id,
                    'contact_id'      => $contact->id,
                    'released_at'     => null,
                ],
                [
                    'held_at'     => now(),
                    'hold_reason' => 'Credit risk class is BLOCKED',
                    'held_by'     => auth()->id(),
                ]
            );

            throw new \InvalidArgumentException(
                'Customer is on credit hold: Credit risk class is BLOCKED'
            );
        }

        // 3. Exposure + new amount vs. limit.
        $exposure = $this->creditManagementService->getCreditExposure($contact);
        $currentBalance = (string) $exposure['total_exposure'];
        $limit          = (string) $creditLimit->credit_limit;
        $newTotal       = bcadd($currentBalance, (string) $newAmount, 4);

        if (bccomp($newTotal, $limit, 4) > 0) {
            // Auto-create a credit hold so the finance team can review and release.
            \App\Models\Accounting\CreditHold::firstOrCreate(
                [
                    'organization_id' => $contact->organization_id,
                    'contact_id'      => $contact->id,
                    'released_at'     => null,
                ],
                [
                    'held_at'     => now(),
                    'hold_reason' => sprintf(
                        'Invoice exceeds credit limit. Balance: %s, Limit: %s, New: %s',
                        number_format((float) $currentBalance, 2),
                        number_format((float) $limit, 2),
                        number_format($newAmount, 2)
                    ),
                    'held_by'     => auth()->id(),
                ]
            );

            throw ApiException::fromError(
                ErrorCodes::SALES_INSUFFICIENT_CREDIT,
                [
                    'balance'   => (float) $currentBalance,
                    'limit'     => (float) $limit,
                    'new_amount' => $newAmount,
                ],
                sprintf(
                    'Invoice exceeds customer credit limit. Balance: %s, Limit: %s, New: %s',
                    number_format((float) $currentBalance, 2),
                    number_format((float) $limit, 2),
                    number_format($newAmount, 2)
                )
            );
        }
    }

    /**
     * Send/post an invoice.
     *
     * Idempotent: a duplicate call with the same invoice within 24 h returns the
     * already-sent invoice without re-posting journals or re-deducting stock.
     */
    public function send(Invoice $invoice, ?string $idempotencyKey = null): Invoice
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw new \InvalidArgumentException('Only draft invoices can be sent.');
        }

        /** @var Invoice $invoice */
        $invoice = $this->withFinancialIdempotency(
            key: $idempotencyKey ?? "invoice:{$invoice->id}:send",
            operation: 'invoice.send',
            orgId: $invoice->organization_id,
            callback: function () use ($invoice): Invoice {
                return $this->executeSend($invoice);
            },
        );

        return $invoice;
    }

    /**
     * Core send logic — delegates to PostInvoiceOrchestrator.
     *
     * Called only once per invoice thanks to the idempotency wrapper in send().
     */
    private function executeSend(Invoice $invoice): Invoice
    {
        return $this->postInvoiceOrchestrator->execute($invoice);
    }

    /**
     * Void an invoice.
     */
    public function void(Invoice $invoice, string $reason = ''): Invoice
    {
        if ($invoice->isPaid()) {
            throw new \InvalidArgumentException('Paid invoices cannot be voided. Create a credit note instead.');
        }

        if ($invoice->status === Invoice::STATUS_VOIDED) {
            throw new \InvalidArgumentException('Invoice is already voided.');
        }

        if ($invoice->status === Invoice::STATUS_PARTIAL) {
            throw new \InvalidArgumentException('Partially-paid invoices cannot be voided. Reverse existing payments first.');
        }

        return DB::transaction(function () use ($invoice, $reason) {
            // Re-fetch with a pessimistic lock to prevent concurrent void operations
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            $hadInventory = in_array($invoice->status, [
                Invoice::STATUS_SENT,
                Invoice::STATUS_PARTIAL,
                Invoice::STATUS_OVERDUE,
                Invoice::STATUS_PAID,
            ], true);

            // Reverse journal entry — propagates on failure to roll back the
            // entire void so the books are never left in an unbalanced state.
            if ($invoice->journal_entry_id && $invoice->journalEntry) {
                $this->journalService->void($invoice->journalEntry, $reason);
            }

            // Return inventory only if stock was previously deducted (i.e. invoice was sent).
            if ($hadInventory) {
                $this->returnInventory($invoice);
            }

            $invoice->update([
                'status' => Invoice::STATUS_VOIDED,
                'notes' => $invoice->notes . "\n\nVoided: " . $reason,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * Create journal entry for invoice.
     */
    protected function createJournalEntry(Invoice $invoice): \App\Models\Accounting\JournalEntry
    {
        return $this->journalEntryFactory->forInvoice($invoice);
    }

    /**
     * Deduct inventory for invoice lines.
     */
    protected function deductInventory(Invoice $invoice): void
    {
        foreach ($invoice->lines as $line) {
            if ($line->product_id && $line->product?->track_inventory && $line->warehouse_id) {
                $this->stockService->recordSale(
                    productId: $line->product_id,
                    warehouseId: $line->warehouse_id,
                    quantity: $line->quantity,
                    variantId: $line->variant_id,
                    referenceNumber: $invoice->invoice_number,
                    referenceId: $invoice->id
                );
            }
        }
    }

    /**
     * Return inventory for voided invoice.
     */
    protected function returnInventory(Invoice $invoice): void
    {
        foreach ($invoice->lines as $line) {
            if ($line->product_id && $line->product?->track_inventory && $line->warehouse_id) {
                $this->stockService->recordMovement(
                    productId: $line->product_id,
                    warehouseId: $line->warehouse_id,
                    movementType: 'return_in',
                    direction: 'in',
                    quantity: $line->quantity,
                    unitCost: $line->product->cost_price ?? 0,
                    variantId: $line->variant_id,
                    referenceType: 'invoice',
                    referenceId: $invoice->id,
                    referenceNumber: $invoice->invoice_number . '-VOID',
                    notes: 'Inventory returned - invoice voided'
                );
            }
        }
    }

    /**
     * Submit invoice to ZATCA compliance.
     *
     * On connection failure the invoice is marked as pending and a retry
     * job is dispatched so the submission is non-blocking.
     *
     * On ZATCA rejection the invoice compliance_status is set to rejected and
     * a warning is logged, but the send() flow is NOT interrupted — the invoice
     * has already been delivered to the customer and compliance is tracked separately.
     */
    protected function submitToZatca(Invoice $invoice): void
    {
        try {
            $result = $this->compliPayClient->submitInvoice($invoice);

            $updateData = [
                'compliance_status' => $result->status,
                'compliance_uuid' => $result->uuid,
                'compliance_hash' => $result->hash,
                'compliance_qr_code' => $result->qrCode,
                'compliance_response' => $result->response,
                'compliance_submitted_at' => now(),
            ];

            // When ZATCA explicitly rejects, record the reason so ops teams can act.
            // Do NOT change invoice status here — the document reached the customer;
            // compliance outcome is a separate concern tracked via compliance_status.
            if ($result->isRejected()) {
                $errorSummary = !empty($result->errors)
                    ? implode('; ', array_map(
                        fn($e) => is_array($e) ? ($e['message'] ?? json_encode($e)) : (string) $e,
                        $result->errors
                    ))
                    : ($result->message ?? 'Unknown error');

                $updateData['compliance_notes'] = 'ZATCA rejected: ' . $errorSummary;

                $invoice->update($updateData);

                $this->logWarning('Invoice sent but ZATCA rejected', [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'errors'         => $result->errors,
                    'message'        => $result->message,
                ]);

                return;
            }

            $invoice->update($updateData);
        } catch (ConnectionException $e) {
            $this->logWarning('ZATCA connection failed, scheduling retry', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);

            $invoice->update([
                'compliance_status' => Invoice::COMPLIANCE_PENDING,
                'compliance_response' => ['error' => $e->getMessage()],
            ]);

            RetryComplianceSubmission::dispatch($invoice->id)->delay(now()->addMinutes(5));
        } catch (\Exception $e) {
            $invoice->update([
                'compliance_status' => Invoice::COMPLIANCE_REJECTED,
                'compliance_response' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    /**
     * Determine if compliance is required.
     */
    protected function determineComplianceStatus(Organization $organization): string
    {
        if (!$organization->requiresCompliance()) {
            return Invoice::COMPLIANCE_NOT_APPLICABLE;
        }

        return Invoice::COMPLIANCE_PENDING;
    }
}
