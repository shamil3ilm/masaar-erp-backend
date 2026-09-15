<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\SupportsAgGrid;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountBalanceService;
use App\Services\Accounting\ChartOfAccountsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    use ReportsBusinessRules;
    use SupportsAgGrid;
    use ValidatesOwnedRows;

    public function __construct(
        private AccountBalanceService $balanceService,
        private ChartOfAccountsService $chartOfAccounts,
    ) {}

    /**
     * List all accounts (tree structure).
     */
    public function index(Request $request): JsonResponse
    {
        $accounts = $this->chartOfAccounts->tree([
            ...$request->only(['type']),
            'active_only' => $request->boolean('active_only'),
        ]);

        return $this->success($this->buildTree($accounts));
    }

    /**
     * List accounts as flat list (for dropdowns).
     */
    public function flat(Request $request): JsonResponse
    {
        $query = $this->chartOfAccounts->flatQuery([
            ...$request->only(['type']),
            'postable' => $request->boolean('postable'),
            'active_only' => $request->boolean('active_only'),
        ]);

        if ($this->isAgGridRequest($request)) {
            return $this->applyAgGrid($query, $request);
        }

        $accounts = $query->get(['id', 'code', 'name', 'account_type', 'sub_type', 'is_header', 'is_active']);

        return $this->success($accounts);
    }

    /**
     * Show single account with balance.
     */
    public function show(Account $account, Request $request): JsonResponse
    {
        $account->load('parent', 'children');

        $data = $account->toArray();

        // Include balance if fiscal year provided
        if ($request->has('fiscal_year_id')) {
            $data['balance'] = $this->balanceService->getAccountBalance(
                $account->id,
                $request->integer('fiscal_year_id'),
                $request->input('as_of_date')
            );
        }

        return $this->success($data);
    }

    /**
     * Create new account.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', $this->ownedBy('chart_of_accounts')],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('chart_of_accounts')
                    ->where('organization_id', auth()->user()->organization_id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'account_type' => ['required', Rule::in(['asset', 'liability', 'equity', 'income', 'expense'])],
            'sub_type' => ['required', 'string'],
            'currency_code' => ['nullable', 'exists:currencies,code'],
            'is_header' => ['boolean'],
        ]);

        $account = $this->chartOfAccounts->create(auth()->user()->organization_id, $validated);

        return $this->success($account, 'Account created successfully', 201);
    }

    /**
     * Update account.
     */
    public function update(Request $request, Account $account): JsonResponse
    {
        // A system account is refused before the payload is validated, so it
        // reports SYSTEM_ACCOUNT whatever was sent.
        if ($account->is_system) {
            return $this->error('System accounts cannot be modified', 'SYSTEM_ACCOUNT', 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'currency_code' => ['nullable', 'exists:currencies,code'],
            'is_active' => ['boolean'],
        ]);

        try {
            $account = $this->chartOfAccounts->update($account, $validated);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($account, 'Account updated successfully');
    }

    /**
     * Delete an account that has no children and no journal lines.
     */
    public function destroy(Account $account): JsonResponse
    {
        try {
            $this->chartOfAccounts->delete($account);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Account deleted successfully');
    }

    /**
     * Get account ledger.
     */
    public function ledger(Account $account, Request $request): JsonResponse
    {
        $ledger = $this->balanceService->getAccountLedger(
            $account->id,
            $request->input('fiscal_year_id'),
            $request->input('start_date'),
            $request->input('end_date'),
            $request->integer('limit', 100),
            $request->integer('offset', 0)
        );

        return $this->success($ledger);
    }

    /**
     * Build tree structure from accounts.
     */
    protected function buildTree($accounts): array
    {
        return $accounts->map(function ($account) {
            $data = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'account_type' => $account->account_type,
                'sub_type' => $account->sub_type,
                'is_header' => $account->is_header,
                'is_system' => $account->is_system,
                'is_active' => $account->is_active,
            ];

            if ($account->children->isNotEmpty()) {
                $data['children'] = $this->buildTree($account->children);
            }

            return $data;
        })->toArray();
    }
}
