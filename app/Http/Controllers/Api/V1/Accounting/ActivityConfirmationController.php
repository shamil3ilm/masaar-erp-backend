<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Accounting\ActivityConfirmation;
use App\Services\Accounting\ActivityConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ActivityConfirmationController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly ActivityConfirmationService $service
    ) {}

    /**
     * List activity confirmations with optional filters.
     *
     * GET /controlling/activity-confirmations
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'cost_center_id'   => $request->filled('cost_center_id') ? $request->integer('cost_center_id') : null,
            'activity_type_id' => $request->filled('activity_type_id') ? $request->integer('activity_type_id') : null,
            'fiscal_year'      => $request->filled('fiscal_year') ? $request->integer('fiscal_year') : null,
            'period'           => $request->filled('period') ? $request->integer('period') : null,
            'status'           => $request->filled('status') ? $request->status : null,
        ];

        return $this->paginated($this->service->list(
            (int) $this->organizationId($request),
            $filters,
            $request->integer('per_page', 25)
        ));
    }

    /**
     * Record an activity confirmation.
     * Captures actual quantity of an activity type performed at a cost centre.
     *
     * POST /controlling/activity-confirmations
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cost_center_id'     => ['required', 'integer', $this->ownedBy('cost_centers')],
            'activity_type_id'   => ['required', 'integer', $this->ownedBy('activity_types')],
            'work_order_id'      => ['nullable', 'integer', $this->ownedBy('work_orders')],
            'work_center_id'     => ['nullable', 'integer', $this->ownedBy('work_centers')],
            'confirmed_quantity' => ['required', 'numeric', 'min:0.0001'],
            'planned_quantity'   => ['nullable', 'numeric', 'min:0'],
            'uom'                => ['nullable', 'string', 'max:20'],
            'actual_rate'        => ['nullable', 'numeric', 'min:0'],
            'planned_rate'       => ['nullable', 'numeric', 'min:0'],
            'fiscal_year'        => ['required', 'integer', 'min:2000', 'max:2100'],
            'period'             => ['required', 'integer', 'min:1', 'max:12'],
            'confirmation_date'  => ['required', 'date'],
            'notes'              => ['nullable', 'string'],
        ]);

        $confirmation = $this->service->record($data, (int) $this->organizationId($request), $request->user()->id);

        return $this->created($confirmation, 'Activity confirmation recorded.');
    }

    /**
     * Show a single activity confirmation.
     *
     * GET /controlling/activity-confirmations/{confirmation}
     */
    public function show(ActivityConfirmation $activityConfirmation): JsonResponse
    {
        $activityConfirmation->load([
            'costCenter:id,code,name',
            'activityType:id,code,name',
            'workOrder:id,order_number',
            'workCenter:id,code,name',
            'confirmedBy:id,name',
            'reversalConfirmation:id,confirmation_number,status',
        ]);

        return $this->success($activityConfirmation);
    }

    /**
     * Reverse a confirmed activity confirmation.
     * Creates a mirror record with negative quantity and marks the original as reversed.
     *
     * POST /controlling/activity-confirmations/{confirmation}/reverse
     */
    public function reverse(Request $request, ActivityConfirmation $activityConfirmation): JsonResponse
    {
        try {
            $reversal = $this->service->reverse($activityConfirmation, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ALREADY_REVERSED', 422);
        }

        return $this->success($reversal, 'Activity confirmation reversed.');
    }
}
