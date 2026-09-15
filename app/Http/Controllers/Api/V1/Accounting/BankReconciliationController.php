<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Accounting\BankReconciliation;
use App\Services\Accounting\BankReconciliationService;
use App\Services\Accounting\EbsParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankReconciliationController extends Controller
{
    use ReportsBusinessRules;
    use ValidatesOwnedRows;

    public function __construct(
        private BankReconciliationService $reconciliationService,
        private EbsParserService $ebsParser,
    ) {}

    /**
     * List bank reconciliations.
     */
    public function index(Request $request): JsonResponse
    {
        $reconciliations = $this->reconciliationService->list(
            $request->only(['bank_account_id', 'status', 'start_date', 'end_date']),
            $request->integer('per_page', 20),
        );

        return $this->paginated($reconciliations);
    }

    /**
     * Create a new bank reconciliation session.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', $this->ownedBy('bank_accounts')],
            'statement_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $reconciliation = $this->reconciliationService->create([
                ...$validated,
                'organization_id' => $this->organizationId($request),
            ], auth()->id());

            return $this->created($reconciliation, 'Bank reconciliation created successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Show a single bank reconciliation with items.
     */
    public function show(BankReconciliation $bankReconciliation): JsonResponse
    {
        $bankReconciliation->load([
            'bankAccount:id,account_name,bank_name,account_number',
            'items.bankTransaction',
            'createdBy:id,name',
            'completedBy:id,name',
        ]);

        return $this->success($bankReconciliation);
    }

    /**
     * Update a bank reconciliation (only in-progress).
     */
    public function update(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        if ($bankReconciliation->status !== BankReconciliation::STATUS_IN_PROGRESS) {
            return $this->error('Only in-progress reconciliations can be updated', 'INVALID_STATUS', 400);
        }

        $validated = $request->validate([
            'statement_balance' => ['sometimes', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $bankReconciliation = $this->reconciliationService->update($bankReconciliation, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATUS', 400);
        }

        return $this->success($bankReconciliation, 'Bank reconciliation updated successfully');
    }

    /**
     * Auto-match bank transactions.
     */
    public function autoMatch(BankReconciliation $bankReconciliation): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->reconciliationService->autoMatch($bankReconciliation, auth()->id()),
            'Auto-matching completed',
            'AUTO_MATCH_FAILED',
        );
    }

    /**
     * Manually match a bank transaction.
     */
    public function manualMatch(Request $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        $validated = $request->validate([
            'bank_transaction_id' => ['required', $this->ownedBy('bank_transactions')],
            'matched_transaction_id' => ['nullable', 'integer'],
            'matched_transaction_type' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $item = $this->reconciliationService->manualMatch(
                $bankReconciliation,
                (int) $validated['bank_transaction_id'],
                auth()->id(),
                $validated['matched_transaction_id'] ?? null,
                $validated['matched_transaction_type'] ?? null
            );

            return $this->created($item, 'Transaction matched successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'MATCH_FAILED', 400);
        }
    }

    /**
     * Unmatch a previously matched transaction.
     */
    public function unmatch(BankReconciliation $bankReconciliation, int $itemId): JsonResponse
    {
        return $this->tryAction(
            function () use ($bankReconciliation, $itemId) { $this->reconciliationService->unmatch($bankReconciliation, $itemId); },
            'Transaction unmatched successfully',
            'UNMATCH_FAILED',
        );
    }

    /**
     * Complete a bank reconciliation.
     */
    public function complete(BankReconciliation $bankReconciliation): JsonResponse
    {
        return $this->tryAction(
            fn() => $this->reconciliationService->complete($bankReconciliation, auth()->id()),
            'Bank reconciliation completed successfully',
            'COMPLETE_FAILED',
            400,
        );
    }

    /**
     * Import a bank statement.
     */
    public function importStatement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', $this->ownedBy('bank_accounts')],
            'file' => ['required', 'file'],
            'file_type' => ['required', 'string', 'in:csv,ofx,qfx,mt940,camt053'],
            'statement_start_date' => ['nullable', 'date'],
            'statement_end_date' => ['nullable', 'date'],
        ]);

        try {
            $file = $request->file('file');
            $path = $file->store('bank-statements', 'private');

            $import = $this->reconciliationService->importStatement([
                'organization_id' => $this->organizationId($request),
                'bank_account_id' => $validated['bank_account_id'],
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $validated['file_type'],
                'statement_start_date' => $validated['statement_start_date'] ?? null,
                'statement_end_date' => $validated['statement_end_date'] ?? null,
            ]);

            return $this->created($import, 'Bank statement import initiated successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'IMPORT_FAILED', 400);
        }
    }

    /**
     * Parse a bank statement import using the EBS parser (MT940 or CAMT.053).
     * Creates BankTransaction records from the parsed statement.
     */
    public function parseStatement(Request $request, int $importId): JsonResponse
    {
        $import = $this->reconciliationService->findImport($this->organizationId($request), $importId);

        try {
            $count = $this->ebsParser->parseStoredImport($import);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(
            ['transactions_imported' => $count],
            "Successfully parsed {$count} transaction(s) from the bank statement."
        );
    }
}
