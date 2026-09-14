<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Controllers\Controller;
use App\Models\Accounting\ParkedDocument;
use App\Services\Accounting\ParkedDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParkedDocumentController extends Controller
{
    use ReportsBusinessRules;

    public function __construct(
        private readonly ParkedDocumentService $parkedDocuments,
    ) {}

    /**
     * List parked documents.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->parkedDocuments->list(
            $request->only(['status', 'document_type', 'date_from', 'date_to']),
            $request->integer('per_page', 20),
        ));
    }

    /**
     * Park a new document.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_type'  => ['required', 'string', 'max:30'],
            'reference'      => ['nullable', 'string', 'max:50'],
            'document_date'  => ['required', 'date'],
            'posting_date'   => ['required', 'date'],
            'document_data'  => ['required', 'array'],
            'total_debit'    => ['required', 'numeric', 'min:0'],
            'total_credit'   => ['required', 'numeric', 'min:0'],
            'currency_code'  => ['nullable', 'string', 'size:3'],
            'parking_reason' => ['nullable', 'string'],
        ]);

        $document = $this->parkedDocuments->park($this->organizationId($request), auth()->id(), $validated);

        return $this->created($document, 'Document parked successfully.');
    }

    /**
     * Update a parked document.
     */
    public function update(Request $request, ParkedDocument $parkedDocument): JsonResponse
    {
        // Refused before the payload is validated, so a document that is no
        // longer parked reports INVALID_STATUS whatever was sent; the service
        // re-checks on the locked row.
        if ($parkedDocument->status !== ParkedDocument::STATUS_PARKED) {
            return $this->error('Only parked documents can be updated.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'reference'      => ['nullable', 'string', 'max:50'],
            'document_date'  => ['sometimes', 'date'],
            'posting_date'   => ['sometimes', 'date'],
            'document_data'  => ['nullable', 'array'],
            'parking_reason' => ['nullable', 'string'],
        ]);

        try {
            $document = $this->parkedDocuments->update($parkedDocument, $validated);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($document, 'Parked document updated successfully.');
    }

    /**
     * Show a single parked document.
     */
    public function show(ParkedDocument $parkedDocument): JsonResponse
    {
        $parkedDocument->load(['parkedBy:id,name', 'approvedBy:id,name']);

        return $this->success($parkedDocument);
    }

    /**
     * Approve a parked or pending-approval document for posting.
     */
    public function approve(ParkedDocument $parkedDocument): JsonResponse
    {
        try {
            $document = $this->parkedDocuments->approve($parkedDocument, auth()->id());
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($document, 'Document approved for posting.');
    }

    /**
     * Post a parked document (convert to a real journal entry).
     */
    public function post(ParkedDocument $parkedDocument): JsonResponse
    {
        try {
            $journalEntry = $this->parkedDocuments->post($parkedDocument, auth()->id());
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'POST_FAILED', 422);
        }

        return $this->success(
            ['parked_document' => $parkedDocument->fresh(), 'journal_entry_id' => $journalEntry->id],
            'Parked document posted successfully.'
        );
    }

    /**
     * Soft-delete a parked document.
     */
    public function destroy(ParkedDocument $parkedDocument): JsonResponse
    {
        try {
            $this->parkedDocuments->delete($parkedDocument);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Parked document deleted.');
    }
}
