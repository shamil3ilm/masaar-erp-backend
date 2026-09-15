<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Sales\SalesOrderCostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesOrderCostingController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly SalesOrderCostingService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'sales_order_id', 'quotation_id']);
        $perPage = $request->integer('per_page', 20);

        return $this->paginated($this->service->list($filters, $perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $validated = $request->validate($this->estimateRules());

        $estimate = $this->service->createEstimate(array_merge($validated, [
            'organization_id' => $orgId,
            'costed_by'       => $request->user()?->id,
        ]));

        return $this->created($estimate);
    }

    public function show(int $id): JsonResponse
    {
        return $this->success($this->service->estimateDetails($id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $estimate = $this->service->estimateOf($id);

        $validated = $request->validate($this->estimateRules());

        return $this->success($this->service->update($estimate, $validated));
    }

    public function addItem(Request $request, int $id): JsonResponse
    {
        $orgId    = $this->organizationId($request);
        $estimate = $this->service->estimateOf($id);

        $validated = $request->validate([
            'sales_order_line_id' => ['nullable', 'integer', $this->ownedThrough('sales_order_lines', 'sales_order_id', 'sales_orders')],
            'product_id'          => ['nullable', 'integer', $this->ownedBy('products')],
            'cost_element_id'     => ['nullable', 'integer', $this->ownedBy('cost_elements')],
            'cost_category'       => ['required', Rule::in(['material', 'labor', 'overhead', 'other'])],
            'quantity'            => ['required', 'numeric', 'min:0'],
            'cost_per_unit'       => ['required', 'numeric', 'min:0'],
            'revenue'             => ['nullable', 'numeric', 'min:0'],
        ]);

        $item = $this->service->addItem($estimate, array_merge($validated, [
            'organization_id' => $orgId,
        ]));

        return $this->created($item);
    }

    public function release(int $id): JsonResponse
    {
        $estimate = $this->service->release($this->service->estimateOf($id));

        return $this->success($estimate, 'Cost estimate released successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function estimateRules(): array
    {
        return [
            'sales_order_id'     => ['nullable', 'integer', $this->ownedBy('sales_orders')],
            'quotation_id'       => ['nullable', 'integer', $this->ownedBy('quotations')],
            'costing_version_id' => ['nullable', 'integer', $this->ownedBy('costing_versions')],
        ];
    }
}
