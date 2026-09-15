<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Sales\IntercompanyBillingDocument;
use App\Models\Sales\IntercompanyPurchaseOrderLink;
use App\Models\Sales\IntercompanySalesOrder;
use App\Models\Sales\IntercompanySalesOrderLine;
use App\Services\Accounting\AccountResolver;
use App\Services\Accounting\JournalService;
use App\Support\TaxMath;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Intercompany sales orders between a selling and a buying organization.
 *
 * An order has no single organization column, so no global scope protects it.
 * Every read goes through ordersVisibleTo(), which admits only orders where
 * the caller's organization is the seller or the buyer. Every status change
 * runs against the locked order or billing document and re-checks the guard
 * there.
 */
class IntercompanySalesService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly AccountResolver $accountResolver,
    ) {}

    /**
     * List intercompany sales orders of the organization, as seller or buyer,
     * latest order date first, narrowed by the filters that are set.
     *
     * @param  array{selling_organization_id?:int, buying_organization_id?:int, status?:string}  $filters
     */
    public function list(int $organizationId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->ordersVisibleTo($organizationId)
            ->with(['sellingOrganization', 'buyingOrganization', 'createdBy'])
            ->latest('order_date');

        if (! empty($filters['selling_organization_id'])) {
            $query->forSellingOrg((int) $filters['selling_organization_id']);
        }

        if (! empty($filters['buying_organization_id'])) {
            $query->forBuyingOrg((int) $filters['buying_organization_id']);
        }

        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        return $query->paginate($perPage);
    }

    /**
     * An order the organization sells or buys.
     *
     * @throws ModelNotFoundException
     */
    public function orderFor(int $organizationId, int|string $id): IntercompanySalesOrder
    {
        return $this->ordersVisibleTo($organizationId)->findOrFail($id);
    }

    /**
     * An order the organization sells or buys, with its lines, purchase order
     * link, billing documents, organizations and creator.
     *
     * @throws ModelNotFoundException
     */
    public function orderDetails(int $organizationId, int|string $id): IntercompanySalesOrder
    {
        return $this->ordersVisibleTo($organizationId)
            ->with([
                'lines.product',
                'purchaseOrderLink',
                'billingDocuments',
                'sellingOrganization',
                'buyingOrganization',
                'createdBy',
            ])
            ->findOrFail($id);
    }

    /**
     * A billing document of the order.
     *
     * @throws ModelNotFoundException
     */
    public function billingDocumentOf(IntercompanySalesOrder $order, int|string $billingDocId): IntercompanyBillingDocument
    {
        return IntercompanyBillingDocument::where('intercompany_sales_order_id', $order->id)->findOrFail($billingDocId);
    }

    /**
     * Create an intercompany sales order with lines and a pending PO link.
     *
     * @param  array{
     *     selling_organization_id: int,
     *     buying_organization_id: int,
     *     order_number: string,
     *     order_date: string,
     *     currency_code?: string,
     *     requested_delivery_date?: string|null,
     *     transfer_price_version_id?: int|null,
     *     notes?: string|null,
     *     created_by?: int|null,
     *     lines: array<int, array{
     *         product_id: int,
     *         line_number: int,
     *         description?: string|null,
     *         quantity: float|string,
     *         unit_of_measure?: string|null,
     *         transfer_price: float|string,
     *         list_price?: float|string|null,
     *         tax_rate?: float|string,
     *     }>
     * }  $data
     */
    public function create(array $data): IntercompanySalesOrder
    {
        return DB::transaction(function () use ($data): IntercompanySalesOrder {
            $linesData = $data['lines'] ?? [];
            unset($data['lines']);

            /** @var IntercompanySalesOrder $order */
            $order = IntercompanySalesOrder::create($data);

            foreach ($linesData as $lineData) {
                $quantity = (string) $lineData['quantity'];
                $transferPrice = (string) $lineData['transfer_price'];
                $taxRate = (string) ($lineData['tax_rate'] ?? '0');
                $amounts = TaxMath::line($quantity, $transferPrice, $taxRate);

                IntercompanySalesOrderLine::create(array_merge($lineData, [
                    'intercompany_sales_order_id' => $order->id,
                    'line_total' => $amounts['subtotal'],
                    'tax_amount' => $amounts['tax'],
                ]));
            }

            $order->recalculateTotals();

            IntercompanyPurchaseOrderLink::create([
                'intercompany_sales_order_id' => $order->id,
                'buying_organization_id' => $order->buying_organization_id,
                'purchase_order_id' => null,
                'status' => 'pending',
            ]);

            return $order->fresh(['lines', 'purchaseOrderLink']);
        });
    }

    /**
     * Update header fields (not lines) of an existing order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(IntercompanySalesOrder $order, array $data): IntercompanySalesOrder
    {
        unset($data['lines']);
        $order->update($data);

        return $order->fresh();
    }

    /**
     * Transition a draft order to confirmed.
     *
     * @throws BusinessRuleException when the order is no longer a draft
     */
    public function confirm(IntercompanySalesOrder $order): IntercompanySalesOrder
    {
        return DB::transaction(function () use ($order): IntercompanySalesOrder {
            $order = $this->lockedOrder($order);

            if (! $order->canConfirm()) {
                throw new BusinessRuleException(
                    "Order [{$order->order_number}] cannot be confirmed from status [{$order->status}].",
                    'INVALID_STATUS'
                );
            }

            $order->update(['status' => IntercompanySalesOrder::STATUS_CONFIRMED]);

            return $order->fresh();
        });
    }

    /**
     * Link the buying organization's purchase order to this order. The
     * caller has checked that the purchase order belongs to the buyer.
     */
    public function linkPurchaseOrder(IntercompanySalesOrder $order, int $purchaseOrderId): IntercompanyPurchaseOrderLink
    {
        return DB::transaction(function () use ($order, $purchaseOrderId): IntercompanyPurchaseOrderLink {
            $order = $this->lockedOrder($order);

            $link = IntercompanyPurchaseOrderLink::firstOrNew([
                'intercompany_sales_order_id' => $order->id,
            ]);

            $link->fill([
                'purchase_order_id' => $purchaseOrderId,
                'buying_organization_id' => $order->buying_organization_id,
                'status' => 'linked',
            ])->save();

            return $link->fresh();
        });
    }

    /**
     * Transition a confirmed order to in_delivery.
     *
     * @throws BusinessRuleException when the order is not confirmed
     */
    public function startDelivery(IntercompanySalesOrder $order): IntercompanySalesOrder
    {
        return DB::transaction(function () use ($order): IntercompanySalesOrder {
            $order = $this->lockedOrder($order);

            if ($order->status !== IntercompanySalesOrder::STATUS_CONFIRMED) {
                throw new BusinessRuleException(
                    "Order [{$order->order_number}] must be confirmed before starting delivery.",
                    'INVALID_STATUS'
                );
            }

            $order->update(['status' => IntercompanySalesOrder::STATUS_IN_DELIVERY]);

            return $order->fresh();
        });
    }

    /**
     * Create a draft intercompany billing document (SAP IV document type).
     *
     * @param  array{
     *     document_number: string,
     *     billing_date: string,
     *     currency_code?: string,
     *     subtotal: float|string,
     *     tax_amount?: float|string,
     *     total_amount: float|string,
     *     notes?: string|null,
     * }  $data
     *
     * @throws BusinessRuleException when the order is not billable
     */
    public function createBillingDocument(IntercompanySalesOrder $order, array $data): IntercompanyBillingDocument
    {
        return DB::transaction(function () use ($order, $data): IntercompanyBillingDocument {
            $order = $this->lockedOrder($order);

            if (! $order->canBill()) {
                throw new BusinessRuleException(
                    "Order [{$order->order_number}] is not in a billable status.",
                    'INVALID_STATUS'
                );
            }

            return IntercompanyBillingDocument::create(array_merge($data, [
                'intercompany_sales_order_id' => $order->id,
                'selling_organization_id' => $order->selling_organization_id,
                'buying_organization_id' => $order->buying_organization_id,
                'status' => IntercompanyBillingDocument::STATUS_DRAFT,
            ]));
        });
    }

    /**
     * Post a draft billing document, post its AR and AP entries and mark the
     * order billed, all in one transaction. The document is re-read under a
     * lock, so it is posted once.
     *
     * @throws BusinessRuleException when the document is no longer a draft
     */
    public function postBillingDocument(IntercompanyBillingDocument $doc): IntercompanyBillingDocument
    {
        return DB::transaction(function () use ($doc): IntercompanyBillingDocument {
            $doc = IntercompanyBillingDocument::query()->lockForUpdate()->findOrFail($doc->id);

            if (! $doc->canPost()) {
                throw new BusinessRuleException(
                    "Billing document [{$doc->document_number}] cannot be posted from status [{$doc->status}].",
                    'INVALID_STATUS'
                );
            }

            $doc->update([
                'status' => IntercompanyBillingDocument::STATUS_POSTED,
                'posted_at' => now(),
            ]);

            // Auto-post AR in the selling org and AP in the buying org
            $journalEntryId = $this->postIcJournalEntries($doc);

            if ($journalEntryId) {
                $doc->update(['journal_entry_id' => $journalEntryId]);
            }

            // Transition the parent order to billed when at least one document is posted
            $order = IntercompanySalesOrder::query()->lockForUpdate()->find($doc->intercompany_sales_order_id);
            if ($order && $order->status !== IntercompanySalesOrder::STATUS_BILLED) {
                $order->update(['status' => IntercompanySalesOrder::STATUS_BILLED]);
            }

            return $doc->fresh(['journalEntry']);
        });
    }

    /**
     * Auto-create AR (selling org) and AP (buying org) journal entries on billing document post.
     * Returns the AR journal entry ID, or null if required accounts cannot be resolved.
     */
    private function postIcJournalEntries(IntercompanyBillingDocument $doc): ?int
    {
        $sellingOrgId = $doc->selling_organization_id;
        $buyingOrgId = $doc->buying_organization_id;
        $amount = (float) $doc->total_amount;
        $ref = $doc->document_number;
        $description = "IC billing: {$ref}";

        $arAccount = $this->accountResolver->bySubType($sellingOrgId, Account::SUBTYPE_RECEIVABLE);

        // Resolve income (IC revenue) account for the selling organization
        $revenueAccount = Account::where('organization_id', $sellingOrgId)
            ->where('account_type', Account::TYPE_INCOME)
            ->where('is_active', true)
            ->first();

        $apAccount = $this->accountResolver->bySubType($buyingOrgId, Account::SUBTYPE_PAYABLE);

        // Resolve expense account for the buying organization
        $expenseAccount = Account::where('organization_id', $buyingOrgId)
            ->where('account_type', Account::TYPE_EXPENSE)
            ->where('is_active', true)
            ->first();

        if (! $arAccount || ! $revenueAccount || ! $apAccount || ! $expenseAccount) {
            // Cannot auto-post without GL accounts — skip silently
            return null;
        }

        // Selling org: DR AR / CR IC Revenue
        $arEntry = $this->journalService->createSimpleEntry(
            organizationId: $sellingOrgId,
            branchId: 0,
            debitAccountId: $arAccount->id,
            creditAccountId: $revenueAccount->id,
            amount: $amount,
            description: $description,
            reference: $ref,
            date: $doc->billing_date?->toDateString() ?? now()->toDateString(),
        );
        $this->journalService->postEntry($arEntry);

        // Buying org: DR IC Expense / CR AP
        $apEntry = $this->journalService->createSimpleEntry(
            organizationId: $buyingOrgId,
            branchId: 0,
            debitAccountId: $expenseAccount->id,
            creditAccountId: $apAccount->id,
            amount: $amount,
            description: $description,
            reference: $ref,
            date: $doc->billing_date?->toDateString() ?? now()->toDateString(),
        );
        $this->journalService->postEntry($apEntry);

        return $arEntry->id;
    }

    /**
     * Cancel a draft or confirmed intercompany sales order and its purchase
     * order link.
     *
     * @throws BusinessRuleException when the order can no longer be cancelled
     */
    public function cancel(IntercompanySalesOrder $order): IntercompanySalesOrder
    {
        return DB::transaction(function () use ($order): IntercompanySalesOrder {
            $order = $this->lockedOrder($order);

            if (! $order->canCancel()) {
                throw new BusinessRuleException(
                    "Order [{$order->order_number}] cannot be cancelled from status [{$order->status}].",
                    'INVALID_STATUS'
                );
            }

            $order->update(['status' => IntercompanySalesOrder::STATUS_CANCELLED]);

            IntercompanyPurchaseOrderLink::where('intercompany_sales_order_id', $order->id)
                ->update(['status' => 'cancelled']);

            return $order->fresh();
        });
    }

    /**
     * Orders in which the organization is the seller or the buyer.
     *
     * @return Builder<IntercompanySalesOrder>
     */
    private function ordersVisibleTo(int $organizationId): Builder
    {
        return IntercompanySalesOrder::query()->where(
            fn (Builder $query) => $query->where('selling_organization_id', $organizationId)
                ->orWhere('buying_organization_id', $organizationId)
        );
    }

    /**
     * The order re-read and locked until the surrounding transaction ends.
     */
    private function lockedOrder(IntercompanySalesOrder $order): IntercompanySalesOrder
    {
        return IntercompanySalesOrder::query()->lockForUpdate()->findOrFail($order->id);
    }
}
