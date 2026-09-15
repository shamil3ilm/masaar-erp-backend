<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Accounting\InterCompanyTransfer;
use App\Services\Accounting\InterCompanyTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterCompanyTransferController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private InterCompanyTransferService $transferService
    ) {}

    /**
     * List inter-company transfers.
     */
    public function index(Request $request): JsonResponse
    {
        $transfers = $this->transferService->list(
            $request->only(['status', 'transfer_type', 'from_branch_id', 'to_branch_id', 'start_date', 'end_date', 'search']),
            $request->integer('per_page', 20),
        );

        return $this->paginated($transfers);
    }

    /**
     * Create a new inter-company transfer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transfer_type' => ['required', 'string', 'in:fund_transfer,loan,investment'],
            'from_branch_id' => ['nullable', $this->ownedBy('branches')],
            'from_bank_account_id' => ['nullable', $this->ownedBy('bank_accounts')],
            'to_branch_id' => ['nullable', $this->ownedBy('branches')],
            'to_bank_account_id' => ['nullable', $this->ownedBy('bank_accounts')],
            'to_organization_id' => ['nullable', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'transfer_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'loan_id' => ['nullable', $this->ownedBy('loans')],
        ]);

        try {
            $transfer = $this->transferService->create([
                ...$validated,
                'organization_id' => $this->organizationId($request),
            ], auth()->id());

            return $this->created($transfer, 'Inter-company transfer created successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Show a single inter-company transfer.
     */
    public function show(InterCompanyTransfer $interCompanyTransfer): JsonResponse
    {
        $interCompanyTransfer->load([
            'fromBranch:id,name',
            'toBranch:id,name',
            'fromBankAccount:id,account_name,bank_name',
            'toBankAccount:id,account_name,bank_name',
            'journalEntry',
            'loan:id,loan_number',
            'createdBy:id,name',
            'approvedBy:id,name',
        ]);

        return $this->success($interCompanyTransfer);
    }

    /**
     * Approve or reject a pending transfer.
     */
    public function review(Request $request, InterCompanyTransfer $interCompanyTransfer): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:500',
        ]);

        return $this->tryAction(
            function () use ($validated, $interCompanyTransfer) {
                return $validated['action'] === 'approve'
                    ? $this->transferService->approve($interCompanyTransfer, auth()->id())
                    : $this->transferService->reject($interCompanyTransfer, $validated['reason'] ?? null);
            },
            $validated['action'] === 'approve' ? 'Transfer approved successfully' : 'Transfer cancelled successfully',
            'VALIDATION_ERROR',
            400
        );
    }

    /**
     * Complete an approved transfer.
     */
    public function complete(InterCompanyTransfer $interCompanyTransfer): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->transferService->complete($interCompanyTransfer),
            'Transfer completed successfully',
            'COMPLETE_FAILED',
            400
        );
    }
}
