<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ecommerce;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Controllers\Controller;
use App\Models\Ecommerce\PaymentGateway;
use App\Services\Ecommerce\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    use ReportsBusinessRules;

    public function __construct(
        private readonly PaymentGatewayService $gateways
    ) {}

    /**
     * List payment gateways.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['provider', 'mode']);

        if ($request->has('is_active')) {
            $filters['is_active'] = $request->boolean('is_active');
        }

        return $this->paginated($this->gateways->paginate($filters, $request->integer('per_page', 15)));
    }

    /**
     * Create a new payment gateway.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|string|in:stripe,paypal,tap,moyasar,hyperpay,mada',
            'credentials' => 'nullable|array',
            'settings' => 'nullable|array',
            'mode' => 'required|string|in:test,live',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'supported_currencies' => 'nullable|array',
            'supported_currencies.*' => 'string|size:3',
            'supported_methods' => 'nullable|array',
            'supported_methods.*' => 'string|max:30',
        ]);

        $gateway = $this->gateways->create($validated, auth()->user()->organization_id);

        return $this->created($gateway, 'Payment gateway created successfully.');
    }

    /**
     * Show a payment gateway.
     */
    public function show(PaymentGateway $paymentGateway): JsonResponse
    {
        return $this->success($paymentGateway);
    }

    /**
     * Update a payment gateway.
     */
    public function update(Request $request, PaymentGateway $paymentGateway): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'credentials' => 'nullable|array',
            'settings' => 'nullable|array',
            'mode' => 'sometimes|string|in:test,live',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'supported_currencies' => 'nullable|array',
            'supported_currencies.*' => 'string|size:3',
            'supported_methods' => 'nullable|array',
            'supported_methods.*' => 'string|max:30',
        ]);

        return $this->success($this->gateways->update($paymentGateway, $validated), 'Payment gateway updated successfully.');
    }

    /**
     * Delete a payment gateway.
     */
    public function destroy(PaymentGateway $paymentGateway): JsonResponse
    {
        try {
            $this->gateways->delete($paymentGateway);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success(null, 'Payment gateway deleted successfully.');
    }
}
