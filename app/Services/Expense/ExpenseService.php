<?php

declare(strict_types=1);

namespace App\Services\Expense;

use App\Exceptions\ERP\BusinessRuleException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseReceipt;
use App\Models\Expense\RecurringExpense;
use App\Services\Core\NumberGeneratorService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExpenseService
{
    public function __construct(
        private readonly NumberGeneratorService $numberGenerator,
    ) {}

    /**
     * The organization's expenses, latest first.
     *
     * @param  array<string, mixed>  $filters  status, category_id, employee_id, start_date, end_date and
     *                                         search apply when set; is_reimbursable applies when present
     */
    public function paginateExpenses(array $filters, int $perPage): LengthAwarePaginator
    {
        return Expense::with(['category:id,name', 'createdBy:id,name'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($filters['start_date'] ?? null, fn ($q, $v) => $q->whereDate('expense_date', '>=', $v))
            ->when($filters['end_date'] ?? null, fn ($q, $v) => $q->whereDate('expense_date', '<=', $v))
            ->when(
                array_key_exists('is_reimbursable', $filters),
                fn ($q) => $q->where('is_reimbursable', $filters['is_reimbursable'])
            )
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('expense_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%");
                });
            })
            ->paginate($perPage);
    }

    /**
     * The organization's recurring expense templates, newest first.
     */
    public function paginateRecurring(?bool $isActive, int $perPage): LengthAwarePaginator
    {
        return RecurringExpense::with(['category:id,name', 'createdBy:id,name'])
            ->orderByDesc('created_at')
            ->when($isActive !== null, fn ($q) => $q->where('is_active', $isActive))
            ->paginate($perPage);
    }

    /**
     * Create a new expense with optional line items.
     */
    public function create(array $data, int $userId): Expense
    {
        return DB::transaction(function () use ($data, $userId) {
            $expense = Expense::create([
                'organization_id' => $data['organization_id'],
                'expense_number' => $this->numberGenerator->generate(Expense::NUMBER_SEQUENCE, Expense::NUMBER_FORMAT, $data['organization_id']),
                'branch_id' => $data['branch_id'] ?? null,
                'category_id' => $data['category_id'],
                'employee_id' => $data['employee_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'expense_date' => $data['expense_date'],
                'due_date' => $data['due_date'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'],
                'currency_code' => $data['currency_code'] ?? 'SAR',
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'amount' => $data['amount'],
                'tax_amount' => $data['tax_amount'] ?? 0,
                'total_amount' => $data['total_amount'] ?? ($data['amount'] + ($data['tax_amount'] ?? 0)),
                'base_amount' => ($data['total_amount'] ?? ($data['amount'] + ($data['tax_amount'] ?? 0))) * ($data['exchange_rate'] ?? 1),
                'is_reimbursable' => $data['is_reimbursable'] ?? false,
                'is_billable' => $data['is_billable'] ?? false,
                'customer_id' => $data['customer_id'] ?? null,
                'account_id' => $data['account_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'custom_fields' => $data['custom_fields'] ?? null,
                'created_by' => $data['created_by'] ?? $userId,
            ]);

            // Create line items if provided
            if (!empty($data['items'])) {
                foreach ($data['items'] as $index => $item) {
                    $expense->items()->create([
                        'category_id' => $item['category_id'] ?? null,
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                        'tax_rate' => $item['tax_rate'] ?? 0,
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'total_amount' => $item['total_amount'] ?? ($item['amount'] + ($item['tax_amount'] ?? 0)),
                        'account_id' => $item['account_id'] ?? null,
                        'line_order' => $item['line_order'] ?? $index,
                    ]);
                }
            }

            return $expense->fresh(['category', 'items', 'createdBy']);
        });
    }

    /**
     * Update an existing expense.
     */
    public function update(Expense $expense, array $data): Expense
    {
        return $expense->lockForTransition(function (Expense $locked) use ($data): Expense {
            if (!in_array($locked->status, [Expense::STATUS_DRAFT, Expense::STATUS_REJECTED])) {
                throw new InvalidArgumentException('Only draft or rejected expenses can be updated.');
            }

            $updateData = collect($data)->only([
                'category_id', 'expense_date', 'due_date', 'payment_method',
                'reference', 'description', 'currency_code', 'exchange_rate',
                'amount', 'tax_amount', 'total_amount', 'is_reimbursable',
                'is_billable', 'customer_id', 'account_id',
                'bank_account_id', 'notes', 'custom_fields',
            ])->filter(fn ($value) => $value !== null)->toArray();

            // Recalculate base amount if amounts changed
            if (isset($updateData['total_amount']) || isset($updateData['exchange_rate'])) {
                $totalAmount = $updateData['total_amount'] ?? $locked->total_amount;
                $exchangeRate = $updateData['exchange_rate'] ?? $locked->exchange_rate;
                $updateData['base_amount'] = $totalAmount * $exchangeRate;
            }

            $locked->update($updateData);

            // Update line items if provided
            if (isset($data['items'])) {
                $locked->items()->delete();
                foreach ($data['items'] as $index => $item) {
                    $locked->items()->create([
                        'category_id' => $item['category_id'] ?? null,
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                        'tax_rate' => $item['tax_rate'] ?? 0,
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'total_amount' => $item['total_amount'] ?? ($item['amount'] + ($item['tax_amount'] ?? 0)),
                        'account_id' => $item['account_id'] ?? null,
                        'line_order' => $item['line_order'] ?? $index,
                    ]);
                }
            }

            // Reset status from rejected to draft if updated
            if ($locked->status === Expense::STATUS_REJECTED) {
                $locked->update(['status' => Expense::STATUS_DRAFT]);
            }

            return $locked->fresh(['category', 'items', 'receipts']);
        });
    }

    /**
     * Deletes a draft expense, checked on the locked row.
     *
     * @throws BusinessRuleException when the expense is past draft
     */
    public function delete(Expense $expense): void
    {
        $expense->lockForTransition(function (Expense $locked): void {
            if ($locked->status !== Expense::STATUS_DRAFT) {
                throw new BusinessRuleException('Only draft expenses can be deleted', 'INVALID_STATUS', 400);
            }

            $locked->delete();
        });
    }

    /**
     * Submit an expense for approval.
     *
     * Each transition checks the status on the locked row, so an expense two
     * requests act on at once moves its budget amounts once.
     */
    public function submit(Expense $expense): Expense
    {
        return $expense->lockForTransition(function (Expense $locked): Expense {
            if ($locked->status !== Expense::STATUS_DRAFT) {
                throw new InvalidArgumentException('Only draft expenses can be submitted.');
            }

            $locked->update(['status' => Expense::STATUS_SUBMITTED]);

            // Update budget committed amount
            $this->updateBudgetCommitted($locked);

            return $locked->fresh();
        });
    }

    /**
     * Approve an expense.
     */
    public function approve(Expense $expense, int $approverId): Expense
    {
        return $expense->lockForTransition(function (Expense $locked) use ($approverId): Expense {
            if ($locked->status !== Expense::STATUS_SUBMITTED) {
                throw new InvalidArgumentException('Only submitted expenses can be approved.');
            }

            $locked->update([
                'status' => Expense::STATUS_APPROVED,
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);

            // Move from committed to spent in budget
            $this->updateBudgetSpent($locked);

            return $locked->fresh(['approvedBy']);
        });
    }

    /**
     * Reject an expense.
     */
    public function reject(Expense $expense, ?string $reason = null): Expense
    {
        return $expense->lockForTransition(function (Expense $locked) use ($reason): Expense {
            if ($locked->status !== Expense::STATUS_SUBMITTED) {
                throw new InvalidArgumentException('Only submitted expenses can be rejected.');
            }

            $locked->update([
                'status' => Expense::STATUS_REJECTED,
                'notes' => $reason ? ($locked->notes ? $locked->notes . "\nRejection: " : "Rejection: ") . $reason : $locked->notes,
            ]);

            // Remove from budget committed amount
            $this->revertBudgetCommitted($locked);

            return $locked->fresh();
        });
    }

    /**
     * Create a recurring expense template.
     */
    public function createRecurring(array $data, int $userId): RecurringExpense
    {
        return DB::transaction(function () use ($data, $userId) {
            return RecurringExpense::create([
                'organization_id' => $data['organization_id'],
                'category_id' => $data['category_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'currency_code' => $data['currency_code'] ?? 'SAR',
                'frequency' => $data['frequency'],
                'frequency_interval' => $data['frequency_interval'] ?? 1,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'next_occurrence' => $data['next_occurrence'] ?? $data['start_date'],
                'max_occurrences' => $data['max_occurrences'] ?? null,
                'auto_approve' => $data['auto_approve'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $data['created_by'] ?? $userId,
            ]);
        });
    }

    /**
     * Process all due recurring expenses.
     */
    public function processRecurring(): array
    {
        $dueRecurring = RecurringExpense::due()->get();
        $created = 0;
        $errors = [];

        foreach ($dueRecurring as $recurring) {
            try {
                DB::transaction(function () use ($recurring, &$created) {
                    $expense = $this->create([
                        'organization_id' => $recurring->organization_id,
                        'category_id' => $recurring->category_id,
                        'supplier_id' => $recurring->supplier_id,
                        'expense_date' => $recurring->next_occurrence->toDateString(),
                        'description' => $recurring->description ?? $recurring->name,
                        'amount' => $recurring->amount,
                        'tax_amount' => 0,
                        'total_amount' => $recurring->amount,
                        'currency_code' => $recurring->currency_code,
                        'is_recurring' => true,
                        'created_by' => $recurring->created_by,
                    ], (int) $recurring->created_by);

                    // Link to recurring expense
                    $expense->update(['recurring_expense_id' => $recurring->id]);

                    // Auto-approve if configured
                    if ($recurring->auto_approve) {
                        $this->submit($expense);
                        $this->approve($expense, (int) $recurring->created_by);
                    }

                    $recurring->advanceNextOccurrence();
                    $created++;
                });
            } catch (\Throwable $e) {
                $errors[] = [
                    'recurring_id' => $recurring->id,
                    'name' => $recurring->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'processed' => $dueRecurring->count(),
            'created' => $created,
            'errors' => $errors,
        ];
    }

    /**
     * Update budget committed amount when expense is submitted.
     */
    private function updateBudgetCommitted(Expense $expense): void
    {
        $budget = \App\Models\Expense\ExpenseBudget::where('organization_id', $expense->organization_id)
            ->where('category_id', $expense->category_id)
            ->where('year', $expense->expense_date->year)
            ->where(function ($q) use ($expense) {
                $q->where('month', $expense->expense_date->month)
                    ->orWhereNull('month');
            })
            ->first();

        if ($budget) {
            $budget->increment('committed_amount', (float) $expense->total_amount);
        }
    }

    /**
     * Move amount from committed to spent when expense is approved.
     */
    private function updateBudgetSpent(Expense $expense): void
    {
        $budget = \App\Models\Expense\ExpenseBudget::where('organization_id', $expense->organization_id)
            ->where('category_id', $expense->category_id)
            ->where('year', $expense->expense_date->year)
            ->where(function ($q) use ($expense) {
                $q->where('month', $expense->expense_date->month)
                    ->orWhereNull('month');
            })
            ->first();

        if ($budget) {
            $budget->decrement('committed_amount', (float) $expense->total_amount);
            $budget->increment('spent_amount', (float) $expense->total_amount);
        }
    }

    /**
     * Revert budget committed amount when expense is rejected.
     */
    private function revertBudgetCommitted(Expense $expense): void
    {
        $budget = \App\Models\Expense\ExpenseBudget::where('organization_id', $expense->organization_id)
            ->where('category_id', $expense->category_id)
            ->where('year', $expense->expense_date->year)
            ->where(function ($q) use ($expense) {
                $q->where('month', $expense->expense_date->month)
                    ->orWhereNull('month');
            })
            ->first();

        if ($budget) {
            $budget->decrement('committed_amount', (float) $expense->total_amount);
        }
    }
}
