<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Sales\Promotion;
use App\Services\Sales\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    use ReportsBusinessRules, ValidatesOwnedRows;

    /** Accepted by validation but not stored on the promotion. */
    private const UNSTORED_FIELDS = ['product_ids', 'customer_ids'];

    public function __construct(
        protected PromotionService $promotionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $promotions = $this->promotionService->list(
            $request->user()->organization_id,
            $request->boolean('active_only'),
            $request->type,
            $request->integer('per_page', 20)
        );

        return $this->paginated($promotions);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules($request, null));

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $promotion = $this->promotionService->create(
                $request->user()->organization_id,
                $request->user()->id,
                Arr::except($validator->validated(), self::UNSTORED_FIELDS)
            );
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->created($promotion, 'Promotion created successfully.');
    }

    public function show(Request $request, Promotion $promotion): JsonResponse
    {
        return $this->success($promotion);
    }

    /**
     * Update the fields a promotion's editor may change. The organization and
     * creator are not among them, so a promotion cannot be moved to another
     * organization.
     */
    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules($request, $promotion));

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $updated = $this->promotionService->update(
            $promotion,
            Arr::except($validator->validated(), self::UNSTORED_FIELDS)
        );

        return $this->success($updated, 'Promotion updated.');
    }

    public function destroy(Request $request, Promotion $promotion): JsonResponse
    {
        $promotion->delete();

        return $this->success(null, 'Promotion deleted.');
    }

    public function validateCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'order_amount' => 'nullable|numeric|min:0',
            'contact_id' => ['nullable', $this->ownedBy('contacts')],
        ]);

        try {
            $promotion = $this->promotionService->checkCode(
                $request->user()->organization_id,
                $request->code,
                $request->contact_id,
                (float) ($request->order_amount ?? 0)
            );
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success([
            'valid' => true,
            'promotion' => $promotion,
            'discount_type' => $promotion->type,
            'discount_value' => $promotion->discount_value,
        ]);
    }

    public function generateCoupons(Request $request, Promotion $promotion): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:1000',
            'prefix' => 'nullable|string|max:10',
            'max_uses_each' => 'nullable|integer|min:1',
        ]);

        try {
            $codes = $this->promotionService->generateCouponCodes(
                $promotion->id,
                $request->integer('quantity'),
                $request->prefix,
                $request->filled('max_uses_each') ? $request->integer('max_uses_each') : null
            );
        } catch (\Exception $e) {
            report($e);

            return $this->error('An unexpected error occurred. Please try again.', 'SERVER_ERROR', 500);
        }

        return $this->success($codes, 'Coupon codes generated.');
    }

    public function coupons(Request $request, Promotion $promotion): JsonResponse
    {
        $coupons = $this->promotionService->listCoupons(
            $promotion,
            $request->boolean('active_only'),
            $request->integer('per_page', 20)
        );

        return $this->paginated($coupons);
    }

    public function analytics(Request $request, Promotion $promotion): JsonResponse
    {
        $analytics = $this->promotionService->getAnalytics($promotion->id);

        return $this->success($analytics);
    }

    /**
     * Rules for creating a promotion, or for updating one when $promotion is
     * given: then every field is optional and the code stays unique within the
     * organization apart from the promotion itself.
     *
     * @return array<string, mixed>
     */
    private function rules(Request $request, ?Promotion $promotion): array
    {
        $required = $promotion === null ? 'required' : 'sometimes';

        return [
            'name' => "{$required}|string|max:255",
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('promotions', 'code')
                    ->where('organization_id', $request->user()->organization_id)
                    ->ignore($promotion?->id),
            ],
            'description' => 'nullable|string|max:2000',
            'type' => "{$required}|in:percentage,fixed_amount,fixed_price,buy_x_get_y,bundle,tiered,free_shipping",
            'apply_to' => 'nullable|in:line,order,shipping',
            'target' => 'nullable|in:all,specific_products,specific_categories,specific_customers,customer_groups',
            'discount_value' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'buy_quantity' => 'nullable|integer|min:1',
            'get_quantity' => 'nullable|integer|min:1',
            'get_discount_percent' => 'nullable|numeric|min:0|max:100',
            'tiers' => 'nullable|array',
            'min_order_amount' => 'nullable|numeric|min:0',
            'min_quantity' => 'nullable|numeric|min:0',
            'start_date' => "{$required}|date",
            'end_date' => $promotion === null ? 'nullable|date|after:start_date' : 'nullable|date',
            'valid_days' => 'nullable|array',
            'valid_time_start' => 'nullable|date_format:H:i',
            'valid_time_end' => 'nullable|date_format:H:i',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_customer' => 'nullable|integer|min:1',
            'is_stackable' => 'nullable|boolean',
            'is_exclusive' => 'nullable|boolean',
            'priority' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'requires_code' => 'nullable|boolean',
            'product_ids' => 'nullable|array',
            'customer_ids' => 'nullable|array',
        ];
    }
}
