<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Resources\Purchase\ThreeWayMatchResultResource;
use App\Services\Purchase\GoodsReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThreeWayMatchController extends Controller
{
    public function __construct(
        private GoodsReceiptService $goodsReceiptService
    ) {}

    /**
     * GET /api/v1/purchase/three-way-match
     * Returns all three-way match results for the organization.
     */
    public function index(Request $request): JsonResponse
    {
        $results = $this->goodsReceiptService->matchResults(
            (int) $request->user()->organization_id,
            $request->only(['match_status', 'bill_id']),
            $request->integer('per_page', 50)
        );

        return $this->paginated($results, ThreeWayMatchResultResource::class);
    }

    /**
     * GET /api/v1/purchase/three-way-match/exceptions
     * Returns only lines with matching exceptions (unmatched).
     */
    public function exceptions(Request $request): JsonResponse
    {
        $exceptions = $this->goodsReceiptService->matchExceptions(
            (int) $request->user()->organization_id,
            $request->integer('per_page', 50)
        );

        return $this->paginated($exceptions, ThreeWayMatchResultResource::class);
    }
}
