<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Core\PrintConfiguration;
use App\Models\Core\PrintTemplate;
use App\Services\Core\PrintTemplateService;
use App\Services\Print\PrintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PrintController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        protected PrintService $printService,
        private readonly PrintTemplateService $templates,
    ) {}

    /**
     * Get available templates for organization.
     */
    public function templates(Request $request): JsonResponse
    {
        return $this->success([
            'data' => $this->templates->listActiveTemplates($request->user()->organization_id),
            'document_types' => PrintTemplate::getDocumentTypes(),
            'paper_sizes' => PrintTemplate::getPaperSizeOptions(),
        ]);
    }

    /**
     * Get single template.
     */
    public function showTemplate(Request $request, int $id): JsonResponse
    {
        return $this->success($this->templates->findTemplate($request->user()->organization_id, $id));
    }

    /**
     * Create custom template.
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:print_templates,code,NULL,id,organization_id,' . $request->user()->organization_id,
            'document_type' => 'required|string|in:' . implode(',', array_keys(PrintTemplate::getDocumentTypes())),
            'paper_size' => 'required|string|in:' . implode(',', array_keys(PrintTemplate::PAPER_SIZES)),
            'orientation' => 'sometimes|string|in:portrait,landscape',
            'template_content' => 'nullable|string',
            'settings' => 'nullable|array',
            'show_logo' => 'sometimes|boolean',
            'show_qr_code' => 'sometimes|boolean',
            'show_signature' => 'sometimes|boolean',
            'show_watermark' => 'sometimes|boolean',
            'watermark_text' => 'nullable|string|max:50',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'is_default' => 'sometimes|boolean',
        ]);

        $data['organization_id'] = $request->user()->organization_id;

        return $this->created($this->templates->createTemplate($data));
    }

    /**
     * Update template.
     */
    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $template = $this->templates->findTemplate($request->user()->organization_id, $id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'settings' => 'nullable|array',
            'template_content' => 'nullable|string',
            'show_logo' => 'sometimes|boolean',
            'show_qr_code' => 'sometimes|boolean',
            'show_signature' => 'sometimes|boolean',
            'show_watermark' => 'sometimes|boolean',
            'watermark_text' => 'nullable|string|max:50',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        return $this->success($this->templates->updateTemplate($template, $data));
    }

    /**
     * Delete template.
     */
    public function destroyTemplate(Request $request, int $id): JsonResponse
    {
        $this->templates->deleteTemplate($this->templates->findTemplate($request->user()->organization_id, $id));

        return $this->success(null, 'Template deleted');
    }

    /**
     * Get printer configurations.
     */
    public function configurations(Request $request): JsonResponse
    {
        return $this->success([
            'data' => $this->templates->listConfigurations($request->user()->organization_id),
            'printer_types' => PrintConfiguration::getPrinterTypes(),
        ]);
    }

    /**
     * Store printer configuration.
     */
    public function storeConfiguration(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', $this->ownedBy('branches')],
            'printer_type' => 'required|string|in:' . implode(',', array_keys(PrintConfiguration::getPrinterTypes())),
            'default_paper_size' => 'required|string',
            'paper_sizes' => 'nullable|array',
            'thermal_settings' => 'nullable|array',
            'margin_settings' => 'nullable|array',
            'font_settings' => 'nullable|array',
            'auto_cut' => 'sometimes|boolean',
            'open_drawer' => 'sometimes|boolean',
            'copies' => 'sometimes|integer|min:1|max:5',
            'is_default' => 'sometimes|boolean',
        ]);

        $data['organization_id'] = $request->user()->organization_id;

        return $this->created($this->templates->createConfiguration($data));
    }

    /**
     * Update printer configuration.
     */
    public function updateConfiguration(Request $request, int $id): JsonResponse
    {
        $config = $this->templates->findConfiguration($request->user()->organization_id, $id);

        $config = $this->templates->updateConfiguration($config, $request->validate([
            'printer_type' => 'sometimes|string',
            'default_paper_size' => 'sometimes|string',
            'thermal_settings' => 'nullable|array',
            'margin_settings' => 'nullable|array',
            'font_settings' => 'nullable|array',
            'auto_cut' => 'sometimes|boolean',
            'open_drawer' => 'sometimes|boolean',
            'copies' => 'sometimes|integer|min:1|max:5',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]));

        return $this->success($config);
    }

    /**
     * Print/Preview invoice.
     */
    public function invoice(Request $request, int $id): Response|JsonResponse
    {
        $invoice = $this->printService->findDocument('invoice', $request->user()->organization_id, $id);

        $paperSize = $request->get('paper_size', 'a4');
        $templateCode = $request->get('template');
        $format = $request->get('format', 'pdf'); // pdf, html, thermal

        if ($format === 'thermal') {
            $printerType = $request->get('printer_type');
            $data = $this->printService->generateThermalData('invoice', $invoice, $printerType);
            return $this->success($data->toArray());
        }

        if ($format === 'html') {
            $html = $this->printService->generateHtml('invoice', $invoice, $paperSize, $templateCode);
            return response($html)->header('Content-Type', 'text/html');
        }

        $pdf = $this->printService->generatePdf('invoice', $invoice, $paperSize, $templateCode);

        $filename = "invoice-{$invoice->invoice_number}.pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Print/Preview quotation.
     */
    public function quotation(Request $request, int $id): Response|JsonResponse
    {
        $quotation = $this->printService->findDocument('quotation', $request->user()->organization_id, $id);

        $paperSize = $request->get('paper_size', 'a4');
        $templateCode = $request->get('template');
        $format = $request->get('format', 'pdf');

        if ($format === 'html') {
            $html = $this->printService->generateHtml('quotation', $quotation, $paperSize, $templateCode);
            return response($html)->header('Content-Type', 'text/html');
        }

        $pdf = $this->printService->generatePdf('quotation', $quotation, $paperSize, $templateCode);

        $filename = "quotation-{$quotation->quotation_number}.pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Print/Preview payment receipt.
     */
    public function paymentReceipt(Request $request, int $id): Response|JsonResponse
    {
        $payment = $this->printService->findDocument('payment_receipt', $request->user()->organization_id, $id);

        $paperSize = $request->get('paper_size', 'a4');
        $templateCode = $request->get('template');
        $format = $request->get('format', 'pdf');

        if ($format === 'thermal') {
            $printerType = $request->get('printer_type');
            $data = $this->printService->generateThermalData('payment_receipt', $payment, $printerType);
            return $this->success($data->toArray());
        }

        if ($format === 'html') {
            $html = $this->printService->generateHtml('payment_receipt', $payment, $paperSize, $templateCode);
            return response($html)->header('Content-Type', 'text/html');
        }

        $pdf = $this->printService->generatePdf('payment_receipt', $payment, $paperSize, $templateCode);

        $filename = "receipt-{$payment->payment_number}.pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Print/Preview purchase order.
     */
    public function purchaseOrder(Request $request, int $id): Response|JsonResponse
    {
        $po = $this->printService->findDocument('purchase_order', $request->user()->organization_id, $id);

        $paperSize = $request->get('paper_size', 'a4');
        $templateCode = $request->get('template');
        $format = $request->get('format', 'pdf');

        if ($format === 'html') {
            $html = $this->printService->generateHtml('purchase_order', $po, $paperSize, $templateCode);
            return response($html)->header('Content-Type', 'text/html');
        }

        $pdf = $this->printService->generatePdf('purchase_order', $po, $paperSize, $templateCode);

        $filename = "po-{$po->po_number}.pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Batch print multiple documents.
     */
    public function batch(Request $request): Response|JsonResponse
    {
        $request->validate([
            'document_type' => 'required|string|in:invoice,quotation,purchase_order,payment_receipt',
            'ids' => 'required|array|min:1|max:50',
            'ids.*' => 'required|integer',
            'paper_size' => 'sometimes|string',
        ]);

        $documentType = $request->get('document_type');
        $paperSize = $request->get('paper_size', 'a4');

        $documents = $this->printService->findDocuments($documentType, $request->user()->organization_id, $request->get('ids'));

        if ($documents->isEmpty()) {
            return $this->notFound('No documents found');
        }

        $pdf = $this->printService->generateBatchPdf($documentType, $documents->all(), $paperSize);

        $filename = "{$documentType}-batch-" . now()->format('Ymd-His') . ".pdf";

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * Initialize default templates for organization.
     */
    public function initializeDefaults(Request $request): JsonResponse
    {
        $created = $this->templates->initializeDefaults($request->user()->organization_id);

        return $this->success(
            ['created' => $created],
            "Created {$created} default templates"
        );
    }
}
