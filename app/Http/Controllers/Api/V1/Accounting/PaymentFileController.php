<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\PaymentFile;
use App\Services\Accounting\PaymentFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentFileController extends Controller
{
    public function __construct(
        private readonly PaymentFileService $service
    ) {}

    /**
     * List payment files for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $files = $this->service->list(
            $request->only(['status', 'file_format']),
            $request->integer('per_page', 25),
        );

        return $this->paginated($files);
    }

    /**
     * Show a payment file with its run.
     */
    public function show(int $id): JsonResponse
    {
        return $this->success($this->service->findWithRun($id));
    }

    /**
     * Generate a payment file from a payment run.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_run_id' => ['required', 'integer', 'exists:payment_runs,id'],
            'file_format'    => [
                'required',
                Rule::in([
                    PaymentFile::FORMAT_SEPA_CT,
                    PaymentFile::FORMAT_SEPA_DD,
                    PaymentFile::FORMAT_ISO20022,
                    PaymentFile::FORMAT_ACH,
                    PaymentFile::FORMAT_BACS,
                    PaymentFile::FORMAT_SWIFT_MT103,
                ]),
            ],
        ]);

        $file = $this->service->generateForRun((int) $validated['payment_run_id'], $validated['file_format']);

        return $this->created($file->makeHidden('file_content'));
    }

    /**
     * Download the file content of a payment file.
     */
    public function download(int $id): JsonResponse
    {
        $file = $this->service->find($id);

        return response()->json([
            'success' => true,
            'data'    => [
                'file_name'    => $file->file_name,
                'file_format'  => $file->file_format,
                'file_content' => $file->file_content,
                'content_type' => 'application/xml',
            ],
            'meta' => [
                'request_id' => request()->header('X-Request-ID', \Illuminate\Support\Str::uuid()),
                'timestamp'  => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Mark a payment file as submitted.
     */
    public function submit(int $id): JsonResponse
    {
        $file = $this->service->find($id);
        $this->service->markSubmitted($file);

        return $this->success($file->fresh(), 'Payment file marked as submitted.');
    }

    /**
     * Mark a payment file as acknowledged.
     */
    public function acknowledge(int $id): JsonResponse
    {
        $file = $this->service->find($id);
        $this->service->markAcknowledged($file);

        return $this->success($file->fresh(), 'Payment file acknowledged.');
    }
}
