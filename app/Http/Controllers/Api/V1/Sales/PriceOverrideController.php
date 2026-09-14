<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Sales\PriceOverride;
use App\Services\Sales\PriceOverrideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceOverrideController extends Controller
{
    use ReportsBusinessRules, ValidatesOwnedRows;

    public function __construct(private PriceOverrideService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->service->list($request->integer('per_page', 20)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'policy_id' => ['required', 'integer', $this->ownedBy('price_override_policies')],
            'document_type' => 'required|string|in:invoice,quotation,sales_order,bill,purchase_order',
            'document_id' => 'nullable|integer',
            'line_item_id' => 'nullable|integer',
            'product_id' => ['nullable', 'integer', $this->ownedBy('products')],
            'variant_id' => ['nullable', 'integer', $this->ownedThrough('product_variants', 'product_id', 'products')],
            'original_price' => 'required|numeric|min:0',
            'override_price' => 'required|numeric|min:0',
            'quantity' => 'required|numeric|min:0.0001',
            'override_type' => 'required|string|in:discount,markup,custom_price,price_match,negotiated,manager_override',
            'reason_code' => 'nullable|string|max:30',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'customer_id' => ['nullable', 'integer', $this->ownedBy('contacts')],
        ]);

        try {
            $override = $this->service->record($validated, auth()->user()->organization_id, auth()->id());
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($override);
    }

    public function show(PriceOverride $override): JsonResponse
    {
        return $this->success($this->service->loadDetails($override));
    }

    public function approve(Request $request, PriceOverride $override): JsonResponse
    {
        try {
            $approved = $this->service->approve($override, auth()->id(), $request->input('approval_notes'));
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($approved);
    }

    public function reject(Request $request, PriceOverride $override): JsonResponse
    {
        $request->validate([
            'approval_notes' => 'required|string',
        ]);

        try {
            $rejected = $this->service->reject($override, auth()->id(), $request->input('approval_notes'));
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($rejected);
    }

    public function report(Request $request): JsonResponse
    {
        $report = $this->service->getOverrideReport(auth()->user()->organization_id, $request->all());

        return $this->success($report);
    }
}
