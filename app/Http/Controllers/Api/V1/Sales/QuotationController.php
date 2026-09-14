<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\Sales\QuotationResource;
use App\Models\Sales\Quotation;
use App\Services\Sales\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $quotationService,
    ) {}

    /**
     * List quotations with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->has('customer_id')) {
            $filters['customer_id'] = $request->integer('customer_id');
        }

        foreach (['status', 'from_date', 'to_date'] as $key) {
            if ($request->has($key)) {
                $filters[$key] = $request->input($key);
            }
        }

        $quotations = $this->quotationService->list($filters, $request->integer('per_page', 15));

        return $this->paginated($quotations, QuotationResource::class);
    }

    /**
     * Create a new quotation.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->organization_id;

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('organization_id', $orgId)],
            'quotation_date' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:quotation_date',
            'currency_code' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'salesperson_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'notes' => 'nullable|string|max:2000',
            'terms_and_conditions' => 'nullable|string|max:5000',
            'reference' => 'nullable|string|max:100',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'lines.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', $orgId)],
            'lines.*.description' => 'required|string|max:500',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_id' => ['nullable', 'integer', Rule::exists('units_of_measure', 'id')->where('organization_id', $orgId)],
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_type' => 'nullable|in:percentage,fixed',
            'lines.*.discount_value' => 'nullable|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_category_id' => ['nullable', 'integer', Rule::exists('tax_categories', 'id')->where('organization_id', $orgId)],
        ]);

        $user = $request->user();
        $branchId = $validated['branch_id']
            ?? $request->attributes->get('branch')?->id
            ?? $user->getDefaultBranch()?->id;

        $quotation = $this->quotationService->create($validated, $user, $branchId);

        return $this->created(new QuotationResource($quotation), 'Quotation created successfully.');
    }

    /**
     * Show a quotation.
     */
    public function show(Quotation $quotation): JsonResponse
    {
        $quotation->load([
            'customer',
            'lines.product',
            'lines.variant',
            'salesperson',
        ]);

        return $this->success(new QuotationResource($quotation));
    }

    /**
     * Update a quotation.
     */
    public function update(Request $request, Quotation $quotation): JsonResponse
    {
        try {
            $this->quotationService->assertEditable($quotation);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        $orgId = $request->user()->organization_id;

        $validated = $request->validate([
            'customer_id' => ['sometimes', 'integer', Rule::exists('contacts', 'id')->where('organization_id', $orgId)],
            'quotation_date' => 'sometimes|date',
            'valid_until' => 'sometimes|date|after_or_equal:quotation_date',
            'currency_code' => 'sometimes|string|size:3',
            'exchange_rate' => 'sometimes|numeric|min:0',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'salesperson_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('organization_id', $orgId)],
            'notes' => 'nullable|string|max:2000',
            'terms_and_conditions' => 'nullable|string|max:5000',
            'reference' => 'nullable|string|max:100',
            'lines' => 'nullable|array|min:1',
            'lines.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('organization_id', $orgId)],
            'lines.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', $orgId)],
            'lines.*.description' => 'required|string|max:500',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.unit_id' => ['nullable', 'integer', Rule::exists('units_of_measure', 'id')->where('organization_id', $orgId)],
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_type' => 'nullable|in:percentage,fixed',
            'lines.*.discount_value' => 'nullable|numeric|min:0',
            'lines.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_category_id' => ['nullable', 'integer', Rule::exists('tax_categories', 'id')->where('organization_id', $orgId)],
        ]);

        return $this->tryAction(
            fn () => new QuotationResource($this->quotationService->update($quotation, $validated)),
            'Quotation updated successfully.'
        );
    }

    /**
     * Delete a draft quotation.
     */
    public function destroy(Quotation $quotation): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->quotationService->delete($quotation),
            'Quotation deleted successfully.'
        );
    }

    /**
     * Send a quotation (change status to sent).
     */
    public function send(Quotation $quotation): JsonResponse
    {
        return $this->tryAction(
            fn () => new QuotationResource($this->quotationService->send($quotation)),
            'Quotation sent successfully.'
        );
    }

    /**
     * Accept or decline a quotation.
     * POST /quotations/{id}/review  {"action": "accept"|"decline"}
     */
    public function review(Request $request, Quotation $quotation): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:accept,decline',
        ]);

        $message = $validated['action'] === 'accept'
            ? 'Quotation accepted successfully.'
            : 'Quotation declined successfully.';

        return $this->tryAction(
            fn () => new QuotationResource($this->quotationService->review($quotation, $validated['action'])),
            $message
        );
    }

    /**
     * Convert an accepted quotation to invoice or sales order.
     */
    public function convert(Request $request, Quotation $quotation): JsonResponse
    {
        try {
            $this->quotationService->assertConvertible($quotation);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        $validated = $request->validate([
            'convert_to' => 'required|in:invoice,sales_order',
        ]);

        return $this->tryAction(
            fn () => $this->quotationService->convert($quotation, $validated['convert_to'], $request->all()),
            'Quotation converted successfully.'
        );
    }
}
