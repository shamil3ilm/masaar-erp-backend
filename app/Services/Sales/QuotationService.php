<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Core\NumberSequence;
use App\Models\Sales\Contact;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationLine;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lists, creates and edits quotations and moves them through their states.
 *
 * Each change to an existing quotation re-reads it under a row lock and checks
 * its status there, so two requests acting on the same quotation cannot both
 * pass a guard that only one of them should.
 */
class QuotationService
{
    public function __construct(
        private readonly InvoiceConversionService $invoiceConversion,
        private readonly SalesOrderService $salesOrderService,
    ) {}

    /**
     * Quotations of the current organization with their customer and
     * salesperson, newest first. Each filter applies when its key is present,
     * even with an empty value.
     *
     * @param  array{customer_id?: int, status?: mixed, from_date?: mixed, to_date?: mixed}  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return Quotation::with(['customer', 'salesperson'])
            ->latest('quotation_date')
            ->when(array_key_exists('customer_id', $filters), fn ($q) => $q->forCustomer($filters['customer_id']))
            ->when(array_key_exists('status', $filters), fn ($q) => $q->where('status', $filters['status']))
            ->when(array_key_exists('from_date', $filters), fn ($q) => $q->where('quotation_date', '>=', $filters['from_date']))
            ->when(array_key_exists('to_date', $filters), fn ($q) => $q->where('quotation_date', '<=', $filters['to_date']))
            ->paginate($perPage);
    }

    /**
     * Create a draft quotation with its lines, numbered in the branch's sequence.
     *
     * @param  array<string, mixed>  $data  validated quotation fields with a 'lines' list
     */
    public function create(array $data, User $user, ?int $branchId): Quotation
    {
        return DB::transaction(function () use ($data, $user, $branchId): Quotation {
            $organizationId = $user->organization_id;
            $quotationNumber = NumberSequence::getNext($organizationId, 'quotation', $branchId);

            // Populate customer details from contact
            $customer = Contact::find($data['customer_id']);

            $quotation = Quotation::create([
                'organization_id' => $organizationId,
                'branch_id' => $branchId,
                'quotation_number' => $quotationNumber,
                'customer_id' => $data['customer_id'],
                'customer_name' => $customer?->getDisplayName() ?? $customer?->company_name ?? $customer?->first_name ?? 'Customer',
                'customer_email' => $customer?->email,
                'quotation_date' => $data['quotation_date'],
                'valid_until' => $data['valid_until'],
                'currency_code' => $data['currency_code'] ?? $user->organization->base_currency ?? 'SAR',
                'exchange_rate' => $data['exchange_rate'] ?? 1.0000,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? 0,
                'salesperson_id' => $data['salesperson_id'] ?? $user->id,
                'notes' => $data['notes'] ?? null,
                'terms_and_conditions' => $data['terms_and_conditions'] ?? null,
                'reference' => $data['reference'] ?? null,
                'status' => Quotation::STATUS_DRAFT,
                'created_by' => $user->id,
            ]);

            $this->createLines($quotation, $data['lines']);

            $quotation->recalculateTotals();
            $quotation->load('lines');

            return $quotation;
        });
    }

    /**
     * @throws \InvalidArgumentException when the quotation is neither a draft nor sent
     */
    public function assertEditable(Quotation $quotation): void
    {
        if (! $quotation->isEditable()) {
            throw new \InvalidArgumentException('Quotation cannot be updated in its current status.');
        }
    }

    /**
     * Update a draft or sent quotation. Fields sent as null keep their value;
     * lines, when given, replace the existing ones.
     *
     * The status is checked on the locked row, so a quotation accepted by a
     * concurrent request is not changed through a copy loaded before that.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException when the quotation can no longer be edited
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        return $quotation->lockForTransition(function (Quotation $quotation) use ($data): Quotation {
            $this->assertEditable($quotation);

            $quotation->update(
                collect($data)->except('lines')->filter(fn ($v) => $v !== null)->toArray()
            );

            if (isset($data['lines'])) {
                $quotation->lines()->delete();
                $this->createLines($quotation, $data['lines']);
                $quotation->recalculateTotals();
            }

            return $quotation->load(['customer', 'lines', 'salesperson']);
        });
    }

    /**
     * Delete a draft quotation, checked on the locked row.
     *
     * @throws \InvalidArgumentException when the quotation is no longer a draft
     */
    public function delete(Quotation $quotation): void
    {
        $quotation->lockForTransition(function (Quotation $quotation): void {
            if ($quotation->status !== Quotation::STATUS_DRAFT) {
                throw new \InvalidArgumentException('Only draft quotations can be deleted.');
            }

            $quotation->delete();
        });
    }

    /**
     * Send a draft or expired quotation, checked on the locked row.
     *
     * @throws \InvalidArgumentException when the quotation is in any other status
     */
    public function send(Quotation $quotation): Quotation
    {
        return $quotation->lockForTransition(function (Quotation $quotation): Quotation {
            if (! in_array($quotation->status, [Quotation::STATUS_DRAFT, Quotation::STATUS_EXPIRED], true)) {
                throw new \InvalidArgumentException('Quotation cannot be sent in its current status.');
            }

            $quotation->transitionTo(Quotation::STATUS_SENT);

            return $quotation->load(['customer', 'lines', 'salesperson']);
        });
    }

    /**
     * Accept or decline a draft or sent quotation, checked on the locked row.
     *
     * @param  'accept'|'decline'  $action
     *
     * @throws \InvalidArgumentException when the quotation is in any other status
     */
    public function review(Quotation $quotation, string $action): Quotation
    {
        return $quotation->lockForTransition(function (Quotation $quotation) use ($action): Quotation {
            if (! in_array($quotation->status, [Quotation::STATUS_SENT, Quotation::STATUS_DRAFT], true)) {
                throw new \InvalidArgumentException('Quotation cannot be reviewed in its current status.');
            }

            $quotation->transitionTo($action === 'accept' ? Quotation::STATUS_ACCEPTED : Quotation::STATUS_DECLINED);

            return $quotation->load(['customer', 'lines', 'salesperson']);
        });
    }

    /**
     * @throws \InvalidArgumentException when the quotation is not accepted
     */
    public function assertConvertible(Quotation $quotation): void
    {
        if (! $quotation->canBeConverted()) {
            throw new \InvalidArgumentException('Only accepted quotations can be converted.');
        }
    }

    /**
     * Convert an accepted quotation into a draft invoice or a draft sales order.
     *
     * The status is checked on the locked row and the quotation is marked
     * converted in the same transaction, so a double submit creates one document.
     *
     * @param  array<string, mixed>  $data  request data handed to the invoice conversion
     * @return array{type: string, id: int, number: string}|null  null for an unknown target
     *
     * @throws \InvalidArgumentException when the quotation is not accepted
     */
    public function convert(Quotation $quotation, string $convertTo, array $data = []): ?array
    {
        return $quotation->lockForTransition(function (Quotation $quotation) use ($convertTo, $data): ?array {
            $this->assertConvertible($quotation);

            if ($convertTo === 'invoice') {
                $invoice = $this->invoiceConversion->createFromQuotation($quotation, $data);

                return ['type' => 'invoice', 'id' => $invoice->id, 'number' => $invoice->invoice_number];
            }

            if ($convertTo === 'sales_order') {
                $salesOrder = $this->salesOrderService->createFromQuotation($quotation);

                $quotation->transitionTo(Quotation::STATUS_CONVERTED);

                return ['type' => 'sales_order', 'id' => $salesOrder->id, 'number' => $salesOrder->order_number];
            }

            return null;
        });
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function createLines(Quotation $quotation, array $lines): void
    {
        foreach ($lines as $order => $lineData) {
            QuotationLine::create([
                'quotation_id' => $quotation->id,
                'product_id' => $lineData['product_id'] ?? null,
                'variant_id' => $lineData['variant_id'] ?? null,
                'description' => $lineData['description'],
                'quantity' => $lineData['quantity'],
                'unit_id' => $lineData['unit_id'] ?? null,
                'unit_price' => $lineData['unit_price'],
                'discount_type' => $lineData['discount_type'] ?? null,
                'discount_value' => $lineData['discount_value'] ?? 0,
                'tax_rate' => $lineData['tax_rate'] ?? 0,
                'tax_category_id' => $lineData['tax_category_id'] ?? null,
                'line_order' => $order + 1,
            ]);
        }
    }
}
