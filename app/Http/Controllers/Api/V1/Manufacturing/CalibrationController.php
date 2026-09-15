<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\CalibrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CalibrationController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private CalibrationService $calibrationService,
    ) {}

    // -------------------------------------------------------------------------
    // Equipment
    // -------------------------------------------------------------------------

    public function equipment(Request $request): JsonResponse
    {
        $filters = [
            ...$this->filledFilters($request, ['category', 'search']),
            'active_only' => $request->boolean('active_only'),
        ];

        return $this->paginated($this->calibrationService->listEquipment(
            $this->organizationId($request),
            $filters,
            $this->perPage($request),
        ));
    }

    public function storeEquipment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'equipment_code'        => 'required|string|max:30',
            'name'                  => 'required|string|max:255',
            'manufacturer'          => 'nullable|string|max:100',
            'model_number'          => 'nullable|string|max:50',
            'serial_number'         => 'nullable|string|max:50',
            'category'              => 'nullable|string|max:50',
            'location'              => 'nullable|string|max:100',
            'responsible_person_id' => ['nullable', 'integer', $this->ownedBy('users')],
            'purchase_date'         => 'nullable|date',
            'is_active'             => 'boolean',
        ]);

        $equipment = $this->calibrationService->createEquipment($this->organizationId($request), $validated);

        return $this->created($equipment);
    }

    public function showEquipment(Request $request, int $id): JsonResponse
    {
        $equipment = $this->calibrationService->findEquipmentForDisplay($this->organizationId($request), $id);

        if ($equipment === null) {
            return $this->notFound('Calibration equipment not found.');
        }

        return $this->success($equipment);
    }

    public function updateEquipment(Request $request, int $id): JsonResponse
    {
        $equipment = $this->calibrationService->findEquipment($this->organizationId($request), $id);

        if ($equipment === null) {
            return $this->notFound('Calibration equipment not found.');
        }

        $validated = $request->validate([
            'equipment_code'        => 'sometimes|string|max:30',
            'name'                  => 'sometimes|string|max:255',
            'manufacturer'          => 'nullable|string|max:100',
            'model_number'          => 'nullable|string|max:50',
            'serial_number'         => 'nullable|string|max:50',
            'category'              => 'nullable|string|max:50',
            'location'              => 'nullable|string|max:100',
            'responsible_person_id' => ['nullable', 'integer', $this->ownedBy('users')],
            'purchase_date'         => 'nullable|date',
            'is_active'             => 'boolean',
        ]);

        return $this->success($this->calibrationService->updateEquipment($equipment, $validated));
    }

    // -------------------------------------------------------------------------
    // Plans
    // -------------------------------------------------------------------------

    public function plans(Request $request): JsonResponse
    {
        $filters = [
            ...$this->filledFilters($request, ['equipment_id']),
            'active_only' => $request->boolean('active_only'),
        ];

        return $this->paginated($this->calibrationService->listPlans(
            $this->organizationId($request),
            $filters,
            $this->perPage($request),
        ));
    }

    public function storePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'calibration_equipment_id'  => ['required', 'integer', $this->ownedBy('calibration_equipment')],
            'plan_code'                 => 'required|string|max:30',
            'calibration_interval_days' => 'required|integer|min:1',
            'tolerance_low'             => 'nullable|numeric',
            'tolerance_high'            => 'nullable|numeric',
            'measurement_unit'          => 'nullable|string|max:20',
            'calibration_procedure'     => 'nullable|string',
            'external_lab'              => 'nullable|string|max:100',
            'is_active'                 => 'boolean',
        ]);

        return $this->created($this->calibrationService->createPlan($this->organizationId($request), $validated));
    }

    public function showPlan(Request $request, int $id): JsonResponse
    {
        $plan = $this->calibrationService->findPlanForDisplay($this->organizationId($request), $id);

        if ($plan === null) {
            return $this->notFound('Calibration plan not found.');
        }

        return $this->success($plan);
    }

    // -------------------------------------------------------------------------
    // Orders
    // -------------------------------------------------------------------------

    public function orders(Request $request): JsonResponse
    {
        return $this->paginated($this->calibrationService->listOrders(
            $this->organizationId($request),
            $this->filledFilters($request, ['status', 'equipment_id', 'result']),
            $this->perPage($request),
        ));
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'calibration_equipment_id' => ['required', 'integer', $this->ownedBy('calibration_equipment')],
            'calibration_plan_id'      => ['nullable', 'integer', $this->ownedBy('calibration_plans')],
            'scheduled_date'           => 'required|date',
            'external_lab'             => 'nullable|string|max:100',
            'notes'                    => 'nullable|string',
        ]);

        $order = $this->calibrationService->createOrder($this->organizationId($request), $validated);

        return $this->created($order->load(['equipment', 'plan']));
    }

    public function showOrder(Request $request, int $id): JsonResponse
    {
        $order = $this->calibrationService->findOrder(
            $this->organizationId($request),
            $id,
            ['equipment', 'plan', 'calibratedBy', 'certificates'],
        );

        if ($order === null) {
            return $this->notFound('Calibration order not found.');
        }

        return $this->success($order);
    }

    public function completeOrder(Request $request, int $id): JsonResponse
    {
        $order = $this->calibrationService->findOrder($this->organizationId($request), $id);

        if ($order === null) {
            return $this->notFound('Calibration order not found.');
        }

        // Checked before validation so a finished order is reported as such;
        // the service checks again on the locked order.
        if (! $order->canBeCompleted()) {
            return $this->invalidStatus('Only planned or in-progress orders can be completed.');
        }

        $validated = $request->validate([
            'result'              => 'required|in:pass,fail,conditional',
            'actual_measurement'  => 'nullable|numeric',
            'calibrated_by'       => ['nullable', 'integer', $this->ownedBy('users')],
            'notes'               => 'nullable|string',
            'certificate'         => 'nullable|array',
            'certificate.certificate_number' => 'required_with:certificate|string|max:50',
            'certificate.issued_date'        => 'nullable|date',
            'certificate.valid_until'        => 'nullable|date',
            'certificate.issued_by'          => 'nullable|string|max:100',
            'certificate.accreditation_body' => 'nullable|string|max:100',
            'certificate.certificate_data'   => 'nullable|array',
        ]);

        try {
            $this->calibrationService->completeCalibration($order, $validated);
        } catch (InvalidArgumentException $e) {
            return $this->invalidStatus($e->getMessage());
        }

        return $this->success($order->fresh(['equipment', 'plan', 'certificates']));
    }

    public function certificates(Request $request, int $orderId): JsonResponse
    {
        $order = $this->calibrationService->findOrder($this->organizationId($request), $orderId);

        if ($order === null) {
            return $this->notFound('Calibration order not found.');
        }

        return $this->success($this->calibrationService->certificatesOf($order));
    }

    // -------------------------------------------------------------------------
    // Alerts & Bulk Actions
    // -------------------------------------------------------------------------

    public function overdue(Request $request): JsonResponse
    {
        $orgId     = $this->organizationId($request);
        $equipment = $this->calibrationService->getOverdueEquipment($orgId);

        return $this->success($equipment);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $days  = (int) $request->input('days', 30);
        $orders = $this->calibrationService->getUpcomingCalibrations($orgId, $days);

        return $this->success($orders);
    }

    public function generateOrders(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $count = $this->calibrationService->generateCalibrationOrders($orgId);

        return $this->success(['generated_count' => $count]);
    }

    /**
     * The named query parameters that were given a value.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function filledFilters(Request $request, array $keys): array
    {
        return array_filter(
            $request->only($keys),
            fn (string $key): bool => $request->filled($key),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->input('per_page', 20), 100);
    }

    private function invalidStatus(string $message): JsonResponse
    {
        return $this->error($message, 'INVALID_STATUS', 422);
    }
}
