<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\ContractResource;
use App\Models\Purchase\Contract;
use App\Services\Purchase\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private ContractService $contractService
    ) {}

    /**
     * List contracts with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $contracts = $this->contractService->list(
            $request->only(['status', 'contract_type', 'contact_id', 'search', 'expiring_in_days']),
            $this->safeSortBy($request->sort_by, ['contract_number', 'title', 'start_date', 'end_date', 'status', 'total_value', 'created_at'], 'created_at'),
            $this->safeSortOrder($request->sort_order, 'desc'),
            $request->integer('per_page', 15),
        );

        return $this->paginated($contracts, ContractResource::class);
    }

    /**
     * Create a new contract.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contract_number' => 'nullable|string|max:30',
            'contract_type' => 'required|in:sales,purchase,service,maintenance',
            'contact_id' => ['required', $this->ownedBy('contacts')],
            'title' => 'required|string|max:200',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'auto_renew' => 'nullable|boolean',
            'renewal_notice_days' => 'nullable|integer|min:0',
            'currency_code' => 'required|string|size:3',
            'total_value' => 'nullable|numeric|min:0',
            'signed_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'branch_id' => ['nullable', $this->ownedBy('branches')],
            'parent_contract_id' => ['nullable', $this->ownedBy('contracts')],
            'lines' => 'nullable|array',
            'lines.*.product_id' => ['nullable', $this->ownedBy('products')],
            'lines.*.description' => 'required|string|max:500',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.line_total' => 'nullable|numeric|min:0',
            'lines.*.unit_id' => ['nullable', $this->ownedBy('units_of_measure')],
            'lines.*.delivery_schedule' => 'nullable|array',
            'lines.*.sort_order' => 'nullable|integer',
            'milestones' => 'nullable|array',
            'milestones.*.milestone_name' => 'required|string|max:200',
            'milestones.*.due_date' => 'required|date',
            'milestones.*.amount' => 'required|numeric|min:0',
            'milestones.*.notes' => 'nullable|string',
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;

        try {
            $contract = $this->contractService->createContract($validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        return $this->created(new ContractResource($contract), 'Contract created successfully.');
    }

    /**
     * Show a contract with all related data.
     */
    public function show(Contract $contract): JsonResponse
    {
        return $this->success(
            new ContractResource(
                $contract->load(['contact', 'lines.product', 'milestones', 'releases', 'documents', 'creator', 'parentContract'])
            )
        );
    }

    /**
     * Update a draft contract.
     */
    public function update(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'auto_renew' => 'nullable|boolean',
            'renewal_notice_days' => 'nullable|integer|min:0',
            'currency_code' => 'sometimes|string|size:3',
            'total_value' => 'nullable|numeric|min:0',
            'signed_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'branch_id' => ['nullable', $this->ownedBy('branches')],
        ]);

        return $this->tryAction(
            fn () => new ContractResource($this->contractService->update($contract, $validated)),
            'Contract updated successfully.'
        );
    }

    /**
     * Delete a draft contract.
     */
    public function destroy(Contract $contract): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->contractService->delete($contract),
            'Contract deleted successfully.'
        );
    }

    /**
     * Activate a draft contract.
     */
    public function activate(Contract $contract): JsonResponse
    {
        return $this->tryAction(
            fn () => new ContractResource($this->contractService->activateContract($contract)),
            'Contract activated successfully.'
        );
    }

    /**
     * Create a release order against a contract.
     */
    public function createRelease(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => 'nullable|string|max:50',
            'source_id' => 'nullable|integer',
            'release_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
        ]);

        return $this->tryAction(
            fn () => $this->contractService->createRelease($contract, $validated)->toArray(),
            'Contract release created successfully.'
        );
    }

    /**
     * List releases for a contract.
     */
    public function indexReleases(Contract $contract): JsonResponse
    {
        return $this->success($this->contractService->releasesOf($contract)->toArray());
    }

    /**
     * Terminate a contract.
     */
    public function terminate(Request $request, Contract $contract): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        return $this->tryAction(
            fn () => new ContractResource($this->contractService->terminateContract($contract, $validated)),
            'Contract terminated successfully.'
        );
    }

    /**
     * Get contracts expiring within specified days.
     */
    public function expiringContracts(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $organization = $this->organization($request);

        if (!$organization) {
            return $this->error('Organization context required.', 'UNAUTHORIZED', 401);
        }

        $contracts = $this->contractService->checkExpiringContracts($organization, $days);

        return $this->success($contracts->map(fn($c) => new ContractResource($c))->values()->toArray());
    }
}
