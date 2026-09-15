<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseRequisition;
use App\Models\Purchase\ReleaseStrategy;
use App\Models\Purchase\ReleaseStrategyApproval;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records an approver's decision on a release level and, once every level of
 * the document is approved, approves the purchase order or requisition the
 * strategy releases.
 *
 * It is separate from ReleaseStrategyService because PurchaseOrderService
 * depends on that service to route orders for approval.
 */
class ReleaseApprovalService
{
    public function __construct(
        private readonly ReleaseStrategyService $releaseStrategyService,
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly PurchaseRequisitionService $purchaseRequisitionService,
    ) {}

    /**
     * Approve one release level; returns whether the document is now fully released.
     *
     * The level decision and the document approval are written together, so
     * a document approval that fails leaves the level pending.
     */
    public function approve(ReleaseStrategyApproval $approval, User $approver, ?string $comments): bool
    {
        return DB::transaction(function () use ($approval, $approver, $comments): bool {
            $fullyReleased = $this->releaseStrategyService->approve($approval, $approver, $comments);

            if ($fullyReleased) {
                $this->approveDocument($approval, $approver->id);
            }

            return $fullyReleased;
        });
    }

    /**
     * Approve the released document of the approval's organization while it
     * still waits for approval.
     */
    private function approveDocument(ReleaseStrategyApproval $approval, int $userId): void
    {
        if ($approval->document_type === ReleaseStrategy::DOCUMENT_TYPE_PURCHASE_ORDER) {
            $order = PurchaseOrder::where('organization_id', $approval->organization_id)->find($approval->document_id);

            if ($order?->isPendingApproval()) {
                $this->purchaseOrderService->approvePO($order, $userId, 'Auto-approved via release strategy.');
            }

            return;
        }

        if ($approval->document_type === ReleaseStrategy::DOCUMENT_TYPE_PURCHASE_REQUISITION) {
            $requisition = PurchaseRequisition::where('organization_id', $approval->organization_id)->find($approval->document_id);

            if ($requisition?->isPendingApproval()) {
                $this->purchaseRequisitionService->approve($requisition);
            }
        }
    }
}
