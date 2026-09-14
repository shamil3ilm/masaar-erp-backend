<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\OffCyclePayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OffCyclePayrollController extends Controller
{
    public function __construct(
        private readonly OffCyclePayrollService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = $this->service->list($request->only([
            'status', 'run_type', 'run_date_from', 'run_date_to', 'per_page',
        ]));

        return $this->paginated($paginated);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'run_type' => 'required|in:bonus,termination,correction,advance_recovery,other',
            'run_name' => 'required|string|max:100',
            'run_date' => 'required|date',
            'notes'    => 'nullable|string',
        ]);

        $run = $this->service->create($validated);

        return $this->created($run->load('processor'), 'Off-cycle payroll run created.');
    }

    public function show(string $id): JsonResponse
    {
        return $this->success($this->service->findWithItems($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $run = $this->service->find($id);

        // Answered before validation so a run that is no longer a draft is
        // reported as such whatever the body holds; the service checks again
        // on the locked row.
        if (! $run->isDraft()) {
            return $this->error('Only draft runs can be updated.', 'INVALID_STATUS', 422);
        }

        $validated = $request->validate([
            'run_name' => 'sometimes|string|max:100',
            'run_date' => 'sometimes|date',
            'notes'    => 'nullable|string',
        ]);

        return $this->tryAction(
            fn() => $this->service->update($run, $validated)->fresh('processor'),
            'Off-cycle payroll run updated.',
            'INVALID_STATUS'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $run = $this->service->find($id);

        try {
            $this->service->delete($run);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATUS', 422);
        }

        return $this->noContent();
    }

    public function addItem(Request $request, string $id): JsonResponse
    {
        $run = $this->service->find($id);

        $validated = $request->validate([
            'employee_id'     => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('organization_id', auth()->user()->organization_id),
            ],
            'component_code'  => 'required|string|max:50',
            'component_name'  => 'required|string|max:100',
            'amount'          => 'required|numeric|min:0',
            'tax_amount'      => 'nullable|numeric|min:0',
            'net_amount'      => 'required|numeric|min:0',
            'notes'           => 'nullable|string',
        ]);

        try {
            $item = $this->service->addItem($run, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATUS', 422);
        }

        return $this->created($item->load('employee'), 'Item added to payroll run.');
    }

    public function removeItem(string $id, string $itemId): JsonResponse
    {
        $run = $this->service->find($id);

        try {
            $this->service->removeItem($run, (int) $itemId);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'INVALID_STATUS', 422);
        }

        return $this->noContent();
    }

    public function process(string $id): JsonResponse
    {
        $run = $this->service->find($id);

        return $this->tryAction(
            fn() => $this->service->process($run)->load(['items.employee', 'processor']),
            'Payroll run processed successfully.',
            'INVALID_STATUS'
        );
    }

    public function cancel(string $id): JsonResponse
    {
        $run = $this->service->find($id);

        return $this->tryAction(
            fn() => $this->service->cancel($run),
            'Payroll run cancelled.',
            'INVALID_STATUS'
        );
    }
}
