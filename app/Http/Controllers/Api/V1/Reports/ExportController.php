<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Sales\Invoice;
use App\Services\Export\ExportService;
use App\Services\Export\InvoiceExportService;
use App\Services\Export\ReportExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private ExportService $exportService,
        private InvoiceExportService $invoiceExportService,
        private ReportExportService $reportExportService
    ) {}

    /**
     * Export invoices to CSV.
     */
    public function exportInvoices(Request $request): BinaryFileResponse|JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|string',
            'customer_id' => ['nullable', $this->ownedBy('contacts')],
        ]);

        $invoices = $this->invoiceExportService->invoicesForExport($request->user()->organization_id, $validated);

        if ($invoices->isEmpty()) {
            return $this->notFound('No invoices found for export.');
        }

        $filePath = $this->invoiceExportService->exportToCsv($invoices);

        return $this->exportService->download($filePath, 'invoices.csv');
    }

    /**
     * Export single invoice to PDF.
     */
    public function exportInvoicePdf(Invoice $invoice): BinaryFileResponse
    {
        $filePath = $this->invoiceExportService->exportToPdf($invoice);

        return $this->exportService->download($filePath, "invoice_{$invoice->invoice_number}.pdf");
    }

    /**
     * Export Trial Balance to CSV.
     */
    public function exportTrialBalance(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        $asOfDate = isset($validated['as_of_date'])
            ? Carbon::parse($validated['as_of_date'])
            : now();

        $filePath = $this->reportExportService->exportTrialBalance($asOfDate);

        return $this->exportService->download($filePath, "trial_balance_{$asOfDate->format('Y-m-d')}.csv");
    }

    /**
     * Export Profit & Loss to CSV.
     */
    public function exportProfitLoss(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $filePath = $this->reportExportService->exportProfitAndLoss($startDate, $endDate);

        return $this->exportService->download($filePath, "profit_loss_{$startDate->format('Y-m-d')}_to_{$endDate->format('Y-m-d')}.csv");
    }

    /**
     * Export Receivable Aging to CSV.
     */
    public function exportReceivableAging(): BinaryFileResponse
    {
        $filePath = $this->reportExportService->exportReceivableAging();

        return $this->exportService->download($filePath, "receivable_aging_" . now()->format('Y-m-d') . ".csv");
    }

    /**
     * Export Payable Aging to CSV.
     */
    public function exportPayableAging(): BinaryFileResponse
    {
        $filePath = $this->reportExportService->exportPayableAging();

        return $this->exportService->download($filePath, "payable_aging_" . now()->format('Y-m-d') . ".csv");
    }
}
