<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Exceptions\ERP\ValidationException as ErpValidationException;
use App\Http\Controllers\Controller;
use App\Models\Sales\CreditNote;
use App\Services\Sales\CreditNoteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreditNoteController extends Controller
{

    public function __construct(
        protected CreditNoteService $creditNoteService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $creditNotes = $this->creditNoteService->list(
            $request->user()->organization_id,
            $request->only(['status', 'contact_id', 'type', 'from_date', 'to_date', 'has_balance']),
            $request->integer('per_page', 20)
        );

        return $this->paginated($creditNotes);
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        // Accept both 'lines' and 'items' keys
        $data = $request->all();
        if (isset($data['lines']) && !isset($data['items'])) {
            $data['items'] = $data['lines'];
        }

        $validator = Validator::make($data, [
            'credit_note_type' => 'required|in:sales,purchase',
            'contact_id' => ['required', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('organization_id', $orgId)],
            'credit_note_date' => 'required|date',
            'currency_code' => 'required|string|size:3',
            'reason' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $creditNote = $this->creditNoteService->create(
                array_merge($data, ['organization_id' => $orgId]),
                $request->user()->id
            );
        } catch (ErpValidationException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getHttpStatus());
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($creditNote, 'Credit note created successfully.');
    }

    public function show(CreditNote $creditNote): JsonResponse
    {
        return $this->success($this->creditNoteService->loadDetails($creditNote));
    }

    public function approve(Request $request, CreditNote $creditNote): JsonResponse
    {
        try {
            $creditNote = $this->creditNoteService->approve($creditNote, $request->user()->id);
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($creditNote, 'Credit note approved.');
    }

    public function apply(Request $request, CreditNote $creditNote): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', Rule::exists('invoices', 'id')->where('organization_id', $request->user()->organization_id)],
            'amount' => 'required|numeric|min:0.01',
        ]);

        try {
            $application = $this->creditNoteService->applyToInvoiceId(
                $creditNote,
                (int) $validated['invoice_id'],
                (float) $validated['amount']
            );
        } catch (ModelNotFoundException $e) {
            // A missing invoice answers 404, as a missing credit note does.
            throw $e;
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\App\Exceptions\ERP\ErpException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getHttpStatus());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($application, 'Credit note applied to invoice.');
    }

    public function void(CreditNote $creditNote): JsonResponse
    {
        try {
            $creditNote = $this->creditNoteService->void($creditNote);
        } catch (\App\Exceptions\ApiException $e) {
            return $this->error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode());
        } catch (\Exception $e) {
            report($e);
            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($creditNote, 'Credit note voided.');
    }
}
