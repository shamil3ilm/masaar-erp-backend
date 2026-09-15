<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AssetCategory;
use App\Models\Accounting\DepreciationRun;
use App\Models\Accounting\FixedAsset;
use App\Services\Accounting\AssetAccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AssetController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private AssetAccountingService $assetService
    ) {}

    // =========================================================================
    // Fixed Assets
    // =========================================================================

    /**
     * List fixed assets with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $assets = $this->assetService->listAssets(
            [
                'status'      => $request->status,
                'category_id' => $request->category_id,
                'branch_id'   => $request->branch_id,
                'search'      => $request->search,
            ],
            $request->integer('per_page', 20)
        );

        return $this->paginated($assets);
    }

    /**
     * Show a single fixed asset.
     */
    public function show(FixedAsset $fixedAsset): JsonResponse
    {
        $fixedAsset->load([
            'category',
            'branch:id,name',
            'createdBy:id,name',
            'transactions' => fn ($q) => $q->orderByDesc('transaction_date')->limit(20),
        ]);

        return $this->success($fixedAsset);
    }

    /**
     * Create a new fixed asset.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_category_id' => ['required', $this->ownedBy('asset_categories')],
            'branch_id' => ['nullable', $this->ownedBy('branches')],
            'asset_number' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0.01'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['required', 'numeric', 'min:0.1'],
            'depreciation_method' => ['nullable', 'string', 'in:' . implode(',', FixedAsset::DEPRECIATION_METHODS)],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $asset = $this->assetService->createAsset(
                array_merge($validated, ['organization_id' => $this->organizationId($request)]),
                auth()->id()
            );

            return $this->created($asset, 'Asset created successfully');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }
    }

    /**
     * Update an existing fixed asset.
     */
    public function update(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        if (in_array($fixedAsset->status, [FixedAsset::STATUS_DISPOSED, FixedAsset::STATUS_WRITTEN_OFF], true)) {
            return $this->error('Disposed or written-off assets cannot be updated.', 'INVALID_STATUS', 400);
        }

        $validated = $request->validate([
            'asset_category_id' => ['sometimes', $this->ownedBy('asset_categories')],
            'branch_id' => ['nullable', $this->ownedBy('branches')],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'useful_life_years' => ['sometimes', 'numeric', 'min:0.1'],
            'depreciation_method' => ['sometimes', 'string', 'in:' . implode(',', FixedAsset::DEPRECIATION_METHODS)],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $asset = $this->assetService->updateAsset($fixedAsset, $validated);

        return $this->success($asset, 'Asset updated successfully');
    }

    /**
     * Soft-delete a fixed asset (only assets that have never been depreciated).
     */
    public function destroy(FixedAsset $fixedAsset): JsonResponse
    {
        if ($fixedAsset->accumulated_depreciation > 0) {
            return $this->error(
                'Assets with recorded depreciation cannot be deleted. Dispose the asset instead.',
                'CANNOT_DELETE',
                400
            );
        }

        $fixedAsset->delete();

        return $this->success(null, 'Asset deleted successfully');
    }

    /**
     * Return the projected depreciation schedule for a fixed asset.
     */
    public function schedule(FixedAsset $fixedAsset): JsonResponse
    {
        $schedule = $this->assetService->getDepreciationSchedule($fixedAsset);

        return $this->success([
            'asset_id' => $fixedAsset->id,
            'asset_number' => $fixedAsset->asset_number,
            'name' => $fixedAsset->name,
            'book_value' => (float) $fixedAsset->book_value,
            'salvage_value' => (float) $fixedAsset->salvage_value,
            'is_fully_depreciated' => $fixedAsset->isFullyDepreciated(),
            'schedule' => $schedule,
        ]);
    }

    /**
     * Dispose a fixed asset (full disposal).
     */
    public function disposeAsset(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $validated = $request->validate([
            'disposal_date' => ['required', 'date'],
            'disposal_amount' => ['nullable', 'numeric', 'min:0'],
            'disposal_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $asset = $this->assetService->disposeAsset($fixedAsset, $validated, auth()->id());

            return $this->success($asset, 'Asset disposed successfully');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'DISPOSAL_FAILED', 400);
        }
    }

    /**
     * Settle an AuC asset to a final fixed asset (SAP AIAB/AIBU).
     *
     * POST /assets/{aucAsset}/settle-auc
     */
    public function settleAuC(Request $request, FixedAsset $fixedAsset): JsonResponse
    {
        $validated = $request->validate([
            'target_asset_id'  => ['required', 'integer', $this->ownedBy('fixed_assets'), 'different:id'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'settlement_date'  => ['required', 'date'],
        ]);

        $targetAsset = $this->assetService->findAsset((int) $validated['target_asset_id']);

        try {
            $result = $this->assetService->settleAuC(
                aucAsset:       $fixedAsset,
                targetAsset:    $targetAsset,
                amount:         (float) $validated['amount'],
                settlementDate: $validated['settlement_date'],
                userId:         $request->user()->id
            );

            return $this->success($result, 'AuC settlement posted successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'SETTLEMENT_FAILED', 422);
        }
    }

    // =========================================================================
    // Asset Categories
    // =========================================================================

    /**
     * List asset categories.
     */
    public function categoriesIndex(Request $request): JsonResponse
    {
        $categories = $this->assetService->listCategories(
            [
                'active' => $request->filled('active')
                    ? filter_var($request->active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'search' => $request->search,
            ],
            $request->integer('per_page', 50)
        );

        return $this->paginated($categories);
    }

    /**
     * Show a single asset category.
     */
    public function categoriesShow(AssetCategory $assetCategory): JsonResponse
    {
        $assetCategory->load([
            'glAssetAccount:id,code,name',
            'glDepreciationAccount:id,code,name',
            'glAccumulatedAccount:id,code,name',
        ]);

        return $this->success($assetCategory);
    }

    /**
     * Create an asset category.
     */
    public function categoriesStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'default_useful_life_years' => ['nullable', 'integer', 'min:1'],
            'default_depreciation_method' => [
                'nullable',
                'string',
                'in:' . implode(',', AssetCategory::DEPRECIATION_METHODS),
            ],
            'default_salvage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'gl_asset_account_id' => ['nullable', $this->ownedBy('chart_of_accounts')],
            'gl_depreciation_account_id' => ['nullable', $this->ownedBy('chart_of_accounts')],
            'gl_accumulated_account_id' => ['nullable', $this->ownedBy('chart_of_accounts')],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $category = $this->assetService->createCategory(
                array_merge($validated, ['organization_id' => $this->organizationId($request)])
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'DUPLICATE_CODE', 422);
        }

        return $this->created($category, 'Asset category created successfully');
    }

    /**
     * Update an asset category.
     */
    public function categoriesUpdate(Request $request, AssetCategory $assetCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'default_useful_life_years' => ['sometimes', 'integer', 'min:1'],
            'default_depreciation_method' => [
                'sometimes',
                'string',
                'in:' . implode(',', AssetCategory::DEPRECIATION_METHODS),
            ],
            'default_salvage_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'gl_asset_account_id' => ['nullable', $this->ownedBy('chart_of_accounts')],
            'gl_depreciation_account_id' => ['nullable', $this->ownedBy('chart_of_accounts')],
            'gl_accumulated_account_id' => ['nullable', $this->ownedBy('chart_of_accounts')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = $this->assetService->updateCategory($assetCategory, $validated);

        return $this->success($category, 'Asset category updated successfully');
    }

    /**
     * Delete an asset category (only if it has no assets).
     */
    public function categoriesDestroy(AssetCategory $assetCategory): JsonResponse
    {
        if ($assetCategory->fixedAssets()->exists()) {
            return $this->error(
                'Cannot delete a category that has associated assets.',
                'CATEGORY_IN_USE',
                400
            );
        }

        $assetCategory->delete();

        return $this->success(null, 'Asset category deleted successfully');
    }

    // =========================================================================
    // Depreciation Runs
    // =========================================================================

    /**
     * Create a new depreciation run (calculates but does not post).
     */
    public function runDepreciation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year_id' => ['required', $this->ownedBy('fiscal_years')],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $run = $this->assetService->runDepreciation(
                array_merge($validated, ['organization_id' => $this->organizationId($request)]),
                auth()->id()
            );

            return $this->created($run, 'Depreciation run created successfully');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'DEPRECIATION_FAILED', 422);
        }
    }

    /**
     * List depreciation runs.
     */
    public function depreciation_runs_index(Request $request): JsonResponse
    {
        $runs = $this->assetService->listDepreciationRuns(
            [
                'status'         => $request->status,
                'fiscal_year_id' => $request->fiscal_year_id,
            ],
            $request->integer('per_page', 20)
        );

        return $this->paginated($runs);
    }

    /**
     * Show a single depreciation run with its lines.
     */
    public function depreciation_runs_show(DepreciationRun $depreciationRun): JsonResponse
    {
        $depreciationRun->load([
            'fiscalYear:id,name',
            'lines.asset:id,asset_number,name',
            'lines.journalEntry:id,entry_number',
            'createdBy:id,name',
            'postedBy:id,name',
        ]);

        return $this->success($depreciationRun);
    }

    /**
     * Post a pending depreciation run (creates journal entries and updates book values).
     */
    public function postRun(Request $request, DepreciationRun $depreciationRun): JsonResponse
    {
        try {
            $run = $this->assetService->postDepreciationRun($depreciationRun, auth()->id());

            return $this->success($run, 'Depreciation run posted successfully');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'POST_FAILED', 400);
        }
    }
}
