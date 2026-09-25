<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Ecommerce\PaymentGateway;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * The payment gateways an organization takes online payments through.
 *
 * Making a gateway the default clears the flag on every other gateway of the
 * organization in the same transaction as the save, so the organization is
 * never left with two defaults or none.
 */
final class PaymentGatewayService
{
    /**
     * @param  array<string, mixed>  $filters  provider, is_active and mode, each applied when present
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return PaymentGateway::query()->latest()
            ->when(array_key_exists('provider', $filters), fn ($q) => $q->byProvider($filters['provider']))
            ->when(array_key_exists('is_active', $filters), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when(array_key_exists('mode', $filters), fn ($q) => $q->where('mode', $filters['mode']))
            ->paginate($perPage);
    }

    public function create(array $data, int $organizationId): PaymentGateway
    {
        return DB::transaction(function () use ($data, $organizationId): PaymentGateway {
            $gateway = PaymentGateway::create(array_merge($data, ['organization_id' => $organizationId]));

            if ($data['is_default'] ?? false) {
                $gateway->setAsDefault();
            }

            return $gateway;
        });
    }

    public function update(PaymentGateway $gateway, array $data): PaymentGateway
    {
        return DB::transaction(function () use ($gateway, $data): PaymentGateway {
            $gateway->update($data);

            if ($data['is_default'] ?? false) {
                $gateway->setAsDefault();
            }

            return $gateway->fresh();
        });
    }

    /**
     * @throws BusinessRuleException when payments were taken through the gateway
     */
    public function delete(PaymentGateway $gateway): void
    {
        if ($gateway->payments()->exists()) {
            throw new BusinessRuleException('Cannot delete gateway with existing payments.', 'VALIDATION_ERROR', 422);
        }

        $gateway->delete();
    }
}
