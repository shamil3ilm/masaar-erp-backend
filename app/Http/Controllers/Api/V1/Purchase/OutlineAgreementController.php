<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Api\V1\Purchase\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\OutlineAgreementResource;
use App\Models\Purchase\OutlineAgreement;
use App\Services\Purchase\OutlineAgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OutlineAgreementController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly OutlineAgreementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $agreements = $this->service->list(
            $this->organizationIdOfUser(),
            $request->only(['vendor_id', 'status', 'agreement_type', 'per_page'])
        );

        return $this->success(
            $agreements->through(fn (OutlineAgreement $agreement) => new OutlineAgreementResource($agreement)),
            'Outline agreements retrieved.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_id'        => ['required', 'integer', $this->ownedBy('contacts')],
            'agreement_number' => 'required|string|max:50',
            'agreement_type'   => 'required|in:quantity_contract,value_contract,scheduling_agreement',
            'valid_from'       => 'required|date',
            'valid_to'         => 'nullable|date|after_or_equal:valid_from',
            'currency_code'    => 'nullable|string|size:3',
            'target_quantity'  => 'nullable|numeric|min:0',
            'target_value'     => 'nullable|numeric|min:0',
            'payment_terms'    => 'nullable|string|max:100',
            'delivery_days'    => 'nullable|integer|min:0',
            'notes'            => 'nullable|string',
        ]);

        $agreement = $this->service->create($this->organizationIdOfUser(), $validated);

        return $this->created(new OutlineAgreementResource($agreement->load(['vendor', 'items'])), 'Outline agreement created.');
    }

    public function show(string $id): JsonResponse
    {
        $agreement = $this->agreement($id, ['vendor', 'items.product', 'releases.purchaseOrder']);

        return $this->success(new OutlineAgreementResource($agreement), 'Outline agreement retrieved.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $agreement = $this->agreement($id);

        $validated = $request->validate([
            'valid_from'    => 'sometimes|date',
            'valid_to'      => 'nullable|date|after_or_equal:valid_from',
            'currency_code' => 'nullable|string|size:3',
            'target_quantity' => 'nullable|numeric|min:0',
            'target_value'  => 'nullable|numeric|min:0',
            'payment_terms' => 'nullable|string|max:100',
            'delivery_days' => 'nullable|integer|min:0',
            'notes'         => 'nullable|string',
        ]);

        return $this->success(
            new OutlineAgreementResource($this->service->update($agreement, $validated)),
            'Outline agreement updated.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($this->agreement($id));

        return $this->success(null, 'Outline agreement deleted.');
    }

    public function addItem(Request $request, string $id): JsonResponse
    {
        $agreement = $this->agreement($id);

        $validated = $request->validate([
            'product_id'      => ['nullable', 'integer', $this->ownedBy('products')],
            'line_number'     => 'required|integer|min:1',
            'description'     => 'nullable|string',
            'target_quantity' => 'nullable|numeric|min:0',
            'target_value'    => 'nullable|numeric|min:0',
            'unit_price'      => 'nullable|numeric|min:0',
            'unit_of_measure' => 'nullable|string|max:20',
        ]);

        $item = $this->service->addItem($agreement, $validated);

        return $this->created($item->load('product'), 'Item added.');
    }

    public function updateItem(Request $request, string $id, string $itemId): JsonResponse
    {
        $item = $this->service->findItem($this->agreement($id), (int) $itemId);

        $validated = $request->validate([
            'description'     => 'nullable|string',
            'target_quantity' => 'nullable|numeric|min:0',
            'target_value'    => 'nullable|numeric|min:0',
            'unit_price'      => 'nullable|numeric|min:0',
            'unit_of_measure' => 'nullable|string|max:20',
        ]);

        return $this->success($this->service->updateItem($item, $validated), 'Item updated.');
    }

    public function createRelease(Request $request, string $id): JsonResponse
    {
        $agreement = $this->agreement($id);

        $validated = $request->validate([
            'outline_agreement_item_id' => [
                'nullable',
                'integer',
                Rule::exists('outline_agreement_items', 'id')->where('outline_agreement_id', $agreement->id),
            ],
            'purchase_order_id'         => ['nullable', 'integer', $this->ownedBy('purchase_orders')],
            'release_date'              => 'required|date',
            'release_quantity'          => 'nullable|numeric|min:0',
            'release_value'             => 'nullable|numeric|min:0',
        ]);

        return $this->created($this->service->createRelease($agreement, $validated), 'Release created.');
    }

    public function getReleases(string $id): JsonResponse
    {
        return $this->success($this->service->releasesOf($this->agreement($id)), 'Releases retrieved.');
    }

    public function activate(string $id): JsonResponse
    {
        $agreement = $this->agreement($id);

        return $this->tryAction(
            fn () => new OutlineAgreementResource($this->service->activate($agreement)),
            'Outline agreement activated.'
        );
    }

    public function cancel(string $id): JsonResponse
    {
        $agreement = $this->agreement($id);

        return $this->tryAction(
            fn () => new OutlineAgreementResource($this->service->cancel($agreement)),
            'Outline agreement cancelled.'
        );
    }

    /**
     * The caller's outline agreement named in the URL.
     *
     * @param  list<string>  $with
     */
    private function agreement(string $id, array $with = []): OutlineAgreement
    {
        return $this->service->find($this->organizationIdOfUser(), (int) $id, $with);
    }

    private function organizationIdOfUser(): int
    {
        return (int) Auth::user()->organization_id;
    }
}
