<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Api\V1\Purchase\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\VendorContractResource;
use App\Services\Purchase\VendorContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorContractController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly VendorContractService $contractService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $contracts = $this->contractService->list(
            (int) $request->user()->organization_id,
            ['status' => $request->input('status'), 'contact_id' => $request->input('contact_id')],
            $request->integer('per_page', 20),
        );

        return $this->paginated($contracts, VendorContractResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'           => ['required', 'integer', $this->ownedBy('contacts')],
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string',
            'contract_type'        => 'nullable|in:supply,service,framework,blanket_order',
            'currency_code'        => 'nullable|string|size:3',
            'total_value'          => 'nullable|numeric|min:0',
            'start_date'           => 'required|date',
            'end_date'             => 'nullable|date|after_or_equal:start_date',
            'auto_renew'           => 'nullable|boolean',
            'renewal_notice_days'  => 'nullable|integer|min:1',
            'payment_terms'        => 'nullable|string',
            'signed_at'            => 'nullable|date',
            'notes'                => 'nullable|string',
            'items'                => 'nullable|array',
            'items.*.product_id'   => ['nullable', 'integer', $this->ownedBy('products')],
            'items.*.description'  => 'required_with:items|string',
            'items.*.unit_price'   => 'required_with:items|numeric|min:0',
            'items.*.quantity'     => 'nullable|numeric|min:0',
            'items.*.unit_of_measure' => 'nullable|string|max:20',
        ]);

        $validated['organization_id'] = $request->user()->organization_id;
        $validated['created_by']      = $request->user()->id;

        $contract = $this->contractService->create($validated);

        return $this->created(new VendorContractResource($contract), 'Vendor contract created.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $contract = $this->contractService->find((int) $request->user()->organization_id, $id, ['items', 'contact']);

        return $this->success(new VendorContractResource($contract));
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        $contract = $this->contractService->find((int) $request->user()->organization_id, $id);

        return $this->success(new VendorContractResource($this->contractService->activate($contract)), 'Contract activated.');
    }

    public function terminate(Request $request, int $id): JsonResponse
    {
        $contract = $this->contractService->find((int) $request->user()->organization_id, $id);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $terminated = $this->contractService->terminate($contract, $validated['reason']);

        return $this->success(new VendorContractResource($terminated), 'Contract terminated.');
    }

    public function expiring(Request $request): JsonResponse
    {
        $contracts = $this->contractService->getExpiringContracts(
            (int) $request->user()->organization_id,
            $request->integer('days', 30),
        );

        return $this->success(VendorContractResource::collection($contracts));
    }
}
