<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\ServiceEntrySheetResource;
use App\Services\Purchase\ServiceProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceEntrySheetController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly ServiceProcurementService $service,
    ) {}

    /**
     * List service entry sheets with filters.
     * SAP equivalent: ML81N (Service Entry Sheet list)
     */
    public function index(Request $request): JsonResponse
    {
        $sheets = $this->service->listEntrySheets(
            $request->only(['status', 'vendor_id', 'service_purchase_order_id', 'search', 'from_date', 'to_date']),
            $request->integer('per_page', 15),
        );

        return $this->paginated($sheets, ServiceEntrySheetResource::class);
    }

    /**
     * Create a new service entry sheet.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_purchase_order_id' => ['required', 'integer', $this->ownedBy('service_purchase_orders')],
            'vendor_id'                 => ['required', 'integer', $this->ownedBy('contacts')],
            'service_period_from'       => ['required', 'date'],
            'service_period_to'         => ['required', 'date', 'after_or_equal:service_period_from'],
            'description'               => ['nullable', 'string', 'max:1000'],
            'lines'                     => ['required', 'array', 'min:1'],
            // Order lines carry no organization column; a line of the caller's order is the caller's.
            'lines.*.service_po_line_id' => [
                'required',
                'integer',
                Rule::exists('service_po_lines', 'id')
                    ->where('service_purchase_order_id', $request->integer('service_purchase_order_id')),
            ],
            'lines.*.quantity'           => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price'         => ['required', 'numeric', 'min:0'],
            'lines.*.description'        => ['nullable', 'string', 'max:500'],
        ]);

        $validated['organization_id'] = $request->user()->organization_id;

        return $this->created(new ServiceEntrySheetResource($this->service->createDraftSES($validated)));
    }

    /**
     * Get a single service entry sheet by UUID.
     */
    public function show(string $uuid): JsonResponse
    {
        $sheet = $this->service->findEntrySheet($uuid, ['vendor', 'servicePurchaseOrder', 'lines', 'submitter', 'approver']);

        return $this->success(new ServiceEntrySheetResource($sheet));
    }

    /**
     * Update a draft service entry sheet.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $sheet = $this->service->findEntrySheet($uuid);

        $validated = $request->validate([
            'service_period_from' => ['sometimes', 'date'],
            'service_period_to'   => ['sometimes', 'date', 'after_or_equal:service_period_from'],
            'description'         => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->tryAction(
            fn () => new ServiceEntrySheetResource($this->service->updateDraftSES($sheet, $validated)),
            'Success',
            'INVALID_STATUS'
        );
    }

    /**
     * Submit a service entry sheet for approval.
     * SAP equivalent: posting SES for acceptance.
     */
    public function submit(Request $request, string $uuid): JsonResponse
    {
        $sheet = $this->service->findEntrySheet($uuid);

        return $this->tryAction(
            fn () => new ServiceEntrySheetResource($this->service->submitDraftSES($sheet, (int) $request->user()->id)),
            'Service entry sheet submitted for approval.',
            'INVALID_STATUS'
        );
    }

    /**
     * Accept or reject a submitted service entry sheet.
     * POST /service-entry-sheets/{uuid}/review  {"action": "accept"|"reject", "rejection_reason": "..."}
     */
    public function review(Request $request, string $uuid): JsonResponse
    {
        $sheet = $this->service->findEntrySheet($uuid);

        $validated = $request->validate([
            'action'           => ['required', 'in:accept,reject'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['action'] === 'accept') {
            return $this->tryAction(
                fn () => new ServiceEntrySheetResource($this->service->approveSES($sheet)),
                'Service entry sheet accepted.',
                'INVALID_STATUS'
            );
        }

        return $this->tryAction(
            fn () => new ServiceEntrySheetResource($this->service->rejectSES($sheet)),
            'Service entry sheet rejected.',
            'INVALID_STATUS'
        );
    }
}
