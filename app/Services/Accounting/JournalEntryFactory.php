<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\ApiException;
use App\Exceptions\ErrorCodes;
use App\Models\Accounting\JournalEntry;
use App\Models\HR\Payslip;
use App\Models\Purchase\Bill;
use App\Models\Purchase\PaymentMade;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentReceived;
use App\Support\Decimal;

/**
 * Centralises all journal-entry construction for financial transactions.
 *
 * Each module (Sales, Purchase, HR) delegates journal posting here so that
 * account-mapping rules, line-building, and header generation live in one
 * place instead of being duplicated across InvoiceService, BillService,
 * PayrollService, PaymentService, and PaymentMadeService.
 */
class JournalEntryFactory
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    /**
     * Sales Invoice: Debit AR, Credit revenue lines + tax payable.
     */
    /**
     * A document's allowance as a positive amount, or null when there is none.
     *
     * A decimal column arrives as a string on one driver and a float on
     * another, so it is compared rather than trusted to be numeric, and a
     * zero or negative value is no allowance at all.
     */
    private function allowance(float|int|string|null $amount): ?string
    {
        $discount = Decimal::at($amount, 4);

        return bccomp($discount, '0', 4) > 0 ? $discount : null;
    }

    /**
     * The account an allowance posts to, or a refusal that says which key to
     * set.
     *
     * Left unset, the line would carry a null account and the entry would be
     * rejected for a missing account_id on an unnamed line - which says
     * nothing about discounts and sends whoever reads it looking in the wrong
     * place.
     */
    private function allowanceAccount(string $key, string $envKey): string
    {
        $account = config("erp.default_accounts.{$key}");

        if (!$account) {
            throw new ApiException(
                ErrorCodes::VALIDATION_FAILED,
                ['config' => "erp.default_accounts.{$key}"],
                "This document carries a discount and no account is configured to post it to. "
                    ."Set {$envKey} to a contra account in the chart of accounts."
            );
        }

        return (string) $account;
    }

    public function forInvoice(Invoice $invoice): JournalEntry
    {
        $customer = $invoice->customer;
        $receivableAccountId = $customer->receivable_account_id ?? config('erp.default_accounts.receivable');

        $lines = [];

        $lines[] = [
            'account_id'  => $receivableAccountId,
            'description' => "Invoice {$invoice->invoice_number} - {$customer->getDisplayName()}",
            'debit'       => $invoice->total,
            'credit'      => 0,
            'contact_id'  => $customer->id,
        ];

        foreach ($invoice->lines as $line) {
            $lines[] = [
                'account_id'  => $line->account_id ?? $line->product?->income_account_id ?? config('erp.default_accounts.sales'),
                'description' => $line->description,
                'debit'       => 0,
                'credit'      => $line->subtotal,
            ];
        }

        if ($invoice->tax_amount > 0) {
            $lines[] = [
                'account_id'  => config('erp.default_accounts.tax_payable'),
                'description' => "VAT/GST on Invoice {$invoice->invoice_number}",
                'debit'       => 0,
                'credit'      => $invoice->tax_amount,
            ];
        }

        // The allowance, debited against revenue.
        //
        // Receivable is debited with the total, which is net of the discount,
        // while the lines are credited with their own subtotals, which are
        // gross of it. Without this line the entry was short by exactly the
        // discount and JournalService refused it - so a discounted invoice
        // could not be posted at all, and nothing noticed because every test
        // that posts mocks either this factory or that service.
        if ($discount = $this->allowance($invoice->discount_amount ?? 0)) {
            $lines[] = [
                'account_id'  => $this->allowanceAccount('sales_discount', 'ERP_ACCOUNT_SALES_DISCOUNT'),
                'description' => "Discount on Invoice {$invoice->invoice_number}",
                'debit'       => $discount,
                'credit'      => 0,
            ];
        }

        return $this->journalService->create([
            'organization_id' => $invoice->organization_id,
            'entry_date'      => $invoice->invoice_date,
            'reference'       => $invoice->invoice_number,
            'description'     => "Sales Invoice - {$customer->getDisplayName()}",
            'source_type'     => Invoice::class,
            'source_id'       => $invoice->id,
            'branch_id'       => $invoice->branch_id,
        ], $lines);
    }

    /**
     * Purchase Bill: Debit expense lines + tax receivable, Credit AP.
     * Returns null when no AP account is configured (e.g. test environments).
     */
    public function forBill(Bill $bill): ?JournalEntry
    {
        $supplier = $bill->supplier;
        $payableAccountId = $supplier->payable_account_id ?? config('erp.default_accounts.payable');

        if (!$payableAccountId) {
            return null;
        }

        $lines = [];

        foreach ($bill->lines as $line) {
            $lines[] = [
                'account_id'  => $line->account_id ?? $line->product?->expense_account_id ?? config('erp.default_accounts.expense'),
                'description' => $line->description,
                'debit'       => $line->subtotal,
                'credit'      => 0,
            ];
        }

        if ($bill->tax_amount > 0) {
            $lines[] = [
                'account_id'  => config('erp.default_accounts.tax_receivable'),
                'description' => "Input VAT/GST on Bill {$bill->bill_number}",
                'debit'       => $bill->tax_amount,
                'credit'      => 0,
            ];
        }

        // The same allowance the other way about: the lines are debited gross
        // and payable is credited net, so the credit side is short by the
        // discount until this is added.
        if ($discount = $this->allowance($bill->discount_amount ?? 0)) {
            $lines[] = [
                'account_id'  => $this->allowanceAccount('purchase_discount', 'ERP_ACCOUNT_PURCHASE_DISCOUNT'),
                'description' => "Discount on Bill {$bill->bill_number}",
                'debit'       => 0,
                'credit'      => $discount,
            ];
        }

        $lines[] = [
            'account_id'  => $payableAccountId,
            'description' => "Bill {$bill->bill_number} - {$supplier->getDisplayName()}",
            'debit'       => 0,
            'credit'      => $bill->total,
            'contact_id'  => $supplier->id,
        ];

        return $this->journalService->create([
            'organization_id' => $bill->organization_id,
            'entry_date'      => $bill->bill_date,
            'reference'       => $bill->bill_number,
            'description'     => "Purchase Bill - {$supplier->getDisplayName()}",
            'source_type'     => Bill::class,
            'source_id'       => $bill->id,
            'branch_id'       => $bill->branch_id,
        ], $lines);
    }

    /**
     * Payslip: Debit gross salary expense, Credit statutory deductions + salary payable.
     *
     * Statutory deductions that have a mapped account get individual credit lines.
     * The remainder (net pay + unmapped deductions) is absorbed by salary payable,
     * keeping the entry balanced: Debit gross = Credit statutory + Credit salary_payable.
     */
    public function forPayslip(Payslip $payslip): JournalEntry
    {
        $salaryExpenseAccount = config('erp.default_accounts.salary_expense');
        $salaryPayableAccount = config('erp.default_accounts.salary_payable');

        $statutoryLines = [];
        $totalStatutoryWithAccount = '0';

        foreach ($payslip->deductions()->with('salaryComponent')->get() as $deduction) {
            if ($deduction->salaryComponent?->is_statutory) {
                $accountId = config("erp.statutory_accounts.{$deduction->salaryComponent->code}");
                if ($accountId) {
                    $statutoryLines[] = [
                        'account_id'  => $accountId,
                        'description' => $deduction->name,
                        'debit'       => 0,
                        'credit'      => $deduction->amount,
                    ];
                    $totalStatutoryWithAccount = bcadd($totalStatutoryWithAccount, (string) $deduction->amount, 4);
                }
            }
        }

        $salaryPayableCredit = bcsub((string) $payslip->gross_earnings, $totalStatutoryWithAccount, 4);

        $lines = [
            [
                'account_id'  => $salaryExpenseAccount,
                'description' => "Salary - {$payslip->employee->getDisplayName()}",
                'debit'       => $payslip->gross_earnings,
                'credit'      => 0,
            ],
            [
                'account_id'  => $salaryPayableAccount,
                'description' => "Salary Payable - {$payslip->employee->getDisplayName()}",
                'debit'       => 0,
                'credit'      => $salaryPayableCredit,
            ],
            ...$statutoryLines,
        ];

        return $this->journalService->create([
            'organization_id' => $payslip->organization_id,
            'entry_date'      => $payslip->payment_date ?? now(),
            'reference'       => $payslip->payslip_number,
            'description'     => "Payroll - {$payslip->employee->getDisplayName()} - {$payslip->payrollPeriod->name}",
            'source_type'     => Payslip::class,
            'source_id'       => $payslip->id,
        ], $lines);
    }

    /**
     * Customer Payment Received: Debit Bank/Cash, Credit AR.
     */
    public function forPaymentReceived(PaymentReceived $payment): JournalEntry
    {
        $customer = $payment->customer;
        $bankAccountId = $payment->bank_account_id ?? config('erp.default_accounts.cash');
        $receivableAccountId = $customer->receivable_account_id ?? config('erp.default_accounts.receivable');

        $lines = [
            [
                'account_id'  => $bankAccountId,
                'description' => "Payment {$payment->payment_number} from {$customer->getDisplayName()}",
                'debit'       => $payment->amount,
                'credit'      => 0,
            ],
            [
                'account_id'  => $receivableAccountId,
                'description' => "Payment {$payment->payment_number}",
                'debit'       => 0,
                'credit'      => $payment->amount,
                'contact_id'  => $customer->id,
            ],
        ];

        return $this->journalService->create([
            'organization_id' => $payment->organization_id,
            'entry_date'      => $payment->payment_date,
            'reference'       => $payment->payment_number,
            'description'     => "Payment Received - {$customer->getDisplayName()}",
            'source_type'     => PaymentReceived::class,
            'source_id'       => $payment->id,
            'branch_id'       => $payment->branch_id,
        ], $lines);
    }

    /**
     * Supplier Payment Made: Debit AP, Credit Bank/Cash.
     */
    public function forPaymentMade(PaymentMade $payment): JournalEntry
    {
        $supplier = $payment->supplier;
        $bankAccountId = $payment->bank_account_id ?? config('erp.default_accounts.cash');
        $payableAccountId = $supplier->payable_account_id ?? config('erp.default_accounts.payable');

        $lines = [
            [
                'account_id'  => $payableAccountId,
                'description' => "Payment {$payment->payment_number} to {$supplier->getDisplayName()}",
                'debit'       => $payment->amount,
                'credit'      => 0,
                'contact_id'  => $supplier->id,
            ],
            [
                'account_id'  => $bankAccountId,
                'description' => "Payment {$payment->payment_number}",
                'debit'       => 0,
                'credit'      => $payment->amount,
            ],
        ];

        return $this->journalService->create([
            'organization_id' => $payment->organization_id,
            'entry_date'      => $payment->payment_date,
            'reference'       => $payment->payment_number,
            'description'     => "Payment Made - {$supplier->getDisplayName()}",
            'source_type'     => PaymentMade::class,
            'source_id'       => $payment->id,
            'branch_id'       => $payment->branch_id,
        ], $lines);
    }
}
