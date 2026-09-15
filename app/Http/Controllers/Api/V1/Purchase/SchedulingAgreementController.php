<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Api\V1\Purchase\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\SchedulingAgreementResource;
use App\Models\Purchase\SchedulingAgreement;
use App\Services\Purchase\SchedulingAgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SchedulingAgreementController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly SchedulingAgreementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $agreements = $this->service->list(
            $this->organizationIdOfUser(),
            $request->only(['vendor_id', 'product_id', 'status', 'per_page'])
        );

        return $this->success(
            $agreements->through(fn (SchedulingAgreement $agreement) => new SchedulingAgreementResource($agreement)),
            'Scheduling agreements retrieved.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id'        => ['required', 'integer', $this->ownedBy('contacts')],
            'product_id'       => ['required', 'integer', $this->ownedBy('products')],
            'agreement_number' => 'required|string|max:50',
            'valid_from'       => 'required|date',
            'valid_to'         => 'nullable|date|after_or_equal:valid_from',
            'target_quantity'  => 'required|numeric|min:0',
            'unit_price'       => 'required|numeric|min:0',
            'currency_code'    => 'nullable|string|size:3',
            'unit_of_measure'  => 'nullable|string|max:20',
            'delivery_days'    => 'nullable|integer|min:0',
            'notes'            => 'nullable|string',
        ]);

        $agreement = $this->service->create($this->organizationIdOfUser(), $validated);

        return $this->created(
            new SchedulingAgreementResource($agreement->load(['vendor', 'product'])),
            'Scheduling agreement created.'
        );
    }

    public function show(string $id): JsonResponse
    {
        return $this->success(
            new SchedulingAgreementResource($this->agreement($id, ['vendor', 'product', 'schedules'])),
            'Scheduling agreement retrieved.'
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $agreement = $this->agreement($id);

        $validated = $request->validate([
            'valid_from'     => 'sometimes|date',
            'valid_to'       => 'nullable|date|after_or_equal:valid_from',
            'target_quantity' => 'sometimes|numeric|min:0',
            'unit_price'     => 'sometimes|numeric|min:0',
            'currency_code'  => 'nullable|string|size:3',
            'unit_of_measure' => 'nullable|string|max:20',
            'delivery_days'  => 'nullable|integer|min:0',
            'notes'          => 'nullable|string',
            'status'         => 'nullable|in:draft,active,expired,cancelled',
        ]);

        return $this->success(
            new SchedulingAgreementResource($this->service->update($agreement, $validated)),
            'Scheduling agreement updated.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($this->agreement($id));

        return $this->success(null, 'Scheduling agreement deleted.');
    }

    public function addSchedule(Request $request, string $id): JsonResponse
    {
        $agreement = $this->agreement($id);

        $validated = $request->validate([
            'schedule_date'      => 'required|date',
            'scheduled_quantity' => 'required|numeric|min:0',
        ]);

        return $this->created($this->service->addScheduleLine($agreement, $validated), 'Schedule line added.');
    }

    public function updateSchedule(Request $request, string $id, string $lineId): JsonResponse
    {
        $line = $this->service->findScheduleLine($this->agreement($id), (int) $lineId);

        $validated = $request->validate([
            'schedule_date'      => 'sometimes|date',
            'scheduled_quantity' => 'sometimes|numeric|min:0',
            'status'             => 'nullable|in:open,partial,complete,cancelled',
        ]);

        return $this->success($this->service->updateScheduleLine($line, $validated), 'Schedule line updated.');
    }

    public function receiveDelivery(Request $request, string $id, string $lineId): JsonResponse
    {
        $line = $this->service->findScheduleLine($this->agreement($id), (int) $lineId);

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
        ]);

        return $this->success(
            $this->service->receiveDelivery($line, (float) $validated['quantity']),
            'Delivery received.'
        );
    }

    public function getSchedules(string $id): JsonResponse
    {
        return $this->success($this->service->schedulesOf($this->agreement($id)), 'Schedules retrieved.');
    }

    /**
     * The caller's scheduling agreement named in the URL.
     *
     * @param  list<string>  $with
     */
    private function agreement(string $id, array $with = []): SchedulingAgreement
    {
        return $this->service->find($this->organizationIdOfUser(), (int) $id, $with);
    }

    private function organizationIdOfUser(): int
    {
        return (int) Auth::user()->organization_id;
    }
}
