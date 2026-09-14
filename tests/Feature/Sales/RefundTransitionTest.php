<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Exceptions\ApiException;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\Refund;
use App\Models\Sales\Wallet;
use App\Services\Sales\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Processing a refund checks the locked refund, so a double submit pays the
 * customer once.
 */
class RefundTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_a_second_process_on_a_stale_refund_is_rejected_and_credits_once(): void
    {
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['sales.refunds.view', 'sales.refunds.process']);

        $customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);

        $refund = Refund::factory()->create([
            'organization_id' => $this->organization->id,
            'refundable_type' => Invoice::class,
            'contact_id' => $customer->id,
            'amount' => 150,
            'currency_code' => 'SAR',
            'refund_method' => Refund::METHOD_WALLET,
            'status' => Refund::STATUS_APPROVED,
            'created_by' => $this->user->id,
        ]);
        $stale = Refund::findOrFail($refund->id);
        $service = app(RefundService::class);

        $service->process($refund, $this->user->id);

        try {
            $service->process($stale, $this->user->id);
            $this->fail('A refund must not be processed twice.');
        } catch (ApiException) {
        }

        $wallet = Wallet::where('contact_id', $customer->id)->sole();
        $this->assertSame(0, bccomp((string) $wallet->balance, '150', 2), "wallet balance is {$wallet->balance}");
        $this->assertSame(1, $wallet->transactions()->count());
    }
}
