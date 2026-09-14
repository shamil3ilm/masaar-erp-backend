<?php

declare(strict_types=1);

namespace App\Models\Expense;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use App\Models\Core\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
class Expense extends Model
{
    use HasFactory;
    use HasUuid;
    use BelongsToOrganization;
    use SoftDeletes;

    /** Numbered by NumberGeneratorService per organization and year: EXP-2026-000001. */
    public const NUMBER_SEQUENCE = 'EXP';
    public const NUMBER_FORMAT = '{prefix}-{year}-{number:6}';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_CARD = 'card';
    public const PAYMENT_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_PETTY_CASH = 'petty_cash';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'expense_number',
        'category_id',
        'employee_id',
        'supplier_id',
        'expense_date',
        'due_date',
        'payment_method',
        'reference',
        'description',
        'currency_code',
        'exchange_rate',
        'amount',
        'tax_amount',
        'total_amount',
        'base_amount',
        'status',
        'is_reimbursable',
        'is_recurring',
        'recurring_expense_id',
        'is_billable',
        'customer_id',
        'account_id',
        'bank_account_id',
        'journal_entry_id',
        'bill_id',
        'notes',
        'custom_fields',
        'created_by',
        'approved_by',
        'approved_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'due_date' => 'date',
            'exchange_rate' => 'decimal:6',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'is_reimbursable' => 'boolean',
            'is_recurring' => 'boolean',
            'is_billable' => 'boolean',
            'custom_fields' => 'array',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            if (!$expense->created_by) {
                $expense->created_by = auth()->id();
            }
            if (!$expense->base_amount) {
                $expense->base_amount = $expense->total_amount * ($expense->exchange_rate ?? 1);
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class)->orderBy('line_order');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ExpenseReceipt::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeReimbursable($query)
    {
        return $query->where('is_reimbursable', true);
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForPeriod($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    public function scopeForCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
}
