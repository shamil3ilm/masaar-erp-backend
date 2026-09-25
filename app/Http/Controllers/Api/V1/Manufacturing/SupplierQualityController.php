<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manufacturing;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Manufacturing\SupplierQualityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierQualityController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(private readonly SupplierQualityService $service) {}

    public function ratings(Request $request): JsonResponse
    {
        return $this->paginated($this->service->listRatings($request->user()->organization_id));
    }

    public function storeRating(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'          => ['required', 'integer', $this->ownedBy('contacts')],
            'rating_period_start'  => 'required|date',
            'rating_period_end'    => 'required|date|after_or_equal:rating_period_start',
            'quality_score'        => 'nullable|numeric|min:0|max:100',
            'delivery_score'       => 'nullable|numeric|min:0|max:100',
            'price_score'          => 'nullable|numeric|min:0|max:100',
            'overall_score'        => 'nullable|numeric|min:0|max:100',
            'classification'       => 'required|in:preferred,approved,conditional,disqualified',
            'notes'                => 'nullable|string',
        ]);

        $rating = $this->service->createRating($request->user()->organization_id, $request->user()->id, $data);

        return $this->created($rating);
    }

    public function avl(Request $request): JsonResponse
    {
        return $this->paginated($this->service->listApprovedVendors($request->user()->organization_id));
    }

    public function storeAvl(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'           => ['required', 'integer', $this->ownedBy('contacts')],
            'product_id'            => ['nullable', 'integer', $this->ownedBy('products')],
            'approved_date'         => 'required|date',
            'expiry_date'           => 'nullable|date|after:approved_date',
            'approval_conditions'   => 'nullable|string',
        ]);

        return $this->created($this->service->approveVendor($request->user()->organization_id, $data));
    }

    public function ncrs(Request $request): JsonResponse
    {
        return $this->paginated($this->service->listNcrs($request->user()->organization_id));
    }

    public function storeNcr(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ncr_number'                    => 'required|string|max:50|unique:supplier_ncr_records',
            'supplier_id'                   => ['required', 'integer', $this->ownedBy('contacts')],
            'product_id'                    => ['nullable', 'integer', $this->ownedBy('products')],
            'po_number'                     => 'nullable|string|max:50',
            'nonconformance_description'    => 'required|string',
            'severity'                      => 'required|in:critical,major,minor',
            'detected_date'                 => 'required|date',
        ]);

        return $this->created($this->service->createNcr($request->user()->organization_id, $data));
    }

    public function closeNcr(Request $request, int $id): JsonResponse
    {
        $ncr  = $this->service->findNcr($request->user()->organization_id, $id);
        $data = $request->validate([
            'disposition' => 'required|in:use_as_is,rework,repair,return_to_supplier,scrap',
        ]);

        return $this->success($this->service->closeNcr($ncr, $data['disposition']), 'NCR closed');
    }
}
