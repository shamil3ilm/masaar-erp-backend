<?php

declare(strict_types=1);

namespace Tests\Feature\Ecommerce;

use App\Models\Core\Organization;
use App\Models\Ecommerce\EcommerceChannel;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\OnlinePayment;
use App\Models\Ecommerce\PaymentGateway;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Sales channels, the orders imported from them, payment gateways and the
 * online payments taken through them.
 *
 * Store and gateway credentials never leave the server, and the customers
 * and invoices these records point to are shown by their reference columns.
 */
class EcommerceEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const PERMISSIONS = [
        'ecommerce.channels.view', 'ecommerce.channels.manage',
        'ecommerce.orders.view', 'ecommerce.orders.manage',
        'ecommerce.payment-gateways.view', 'ecommerce.payment-gateways.manage',
        'ecommerce.payments.view', 'ecommerce.payments.manage',
    ];

    private Organization $otherOrganization;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(self::PERMISSIONS);
        $this->otherOrganization = Organization::factory()->create();

        $this->customer = Contact::factory()->create(['organization_id' => $this->organization->id, 'company_name' => 'Web Buyer LLC']);
    }

    // ----------------------------------------------------------------
    // Channels
    // ----------------------------------------------------------------

    public function test_channels_hide_their_credentials_and_show_the_default_customer_by_reference(): void
    {
        $channel = $this->channel(['credentials' => ['access_token' => 'shpat_secret_token'], 'default_customer_id' => $this->customer->id]);
        $this->channel([], $this->otherOrganization->id);

        $listed = $this->apiGet('/ecommerce/channels')->assertOk()->assertJsonCount(1, 'data');
        $shown = $this->apiGet('/ecommerce/channels/'.$channel->getRouteKey())->assertOk();

        foreach ([$listed->json('data.0'), $shown->json('data')] as $body) {
            $this->assertArrayNotHasKey('credentials', $body);
            $this->assertSame(Contact::REFERENCE_COLUMNS, array_keys($body['default_customer']));
        }

        $this->assertStringNotContainsString('shpat_secret_token', $listed->getContent().$shown->getContent());
    }

    public function test_a_channel_is_created_without_echoing_its_credentials(): void
    {
        $response = $this->apiPost('/ecommerce/channels', [
            'name' => 'Main store',
            'platform' => 'shopify',
            'credentials' => ['access_token' => 'shpat_new_token'],
        ])->assertCreated()
            ->assertJsonPath('message', 'E-commerce channel created successfully.')
            ->assertJsonPath('data.organization_id', $this->organization->id);

        $this->assertStringNotContainsString('shpat_new_token', $response->getContent());
    }

    public function test_a_channel_refuses_a_warehouse_or_customer_of_another_organization(): void
    {
        $foreignWarehouse = Warehouse::factory()->create(['organization_id' => $this->otherOrganization->id]);
        $foreignCustomer = Contact::factory()->create(['organization_id' => $this->otherOrganization->id]);

        $this->apiPost('/ecommerce/channels', [
            'name' => 'Probe',
            'platform' => 'shopify',
            'default_warehouse_id' => $foreignWarehouse->id,
            'default_customer_id' => $foreignCustomer->id,
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, DB::table('ecommerce_channels')->count());
    }

    public function test_a_channel_with_orders_is_kept_and_one_without_is_deleted(): void
    {
        $withOrders = $this->channel();
        $this->order($withOrders);
        $empty = $this->channel();

        $this->apiDelete('/ecommerce/channels/'.$withOrders->getRouteKey())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->apiDelete('/ecommerce/channels/'.$empty->getRouteKey())
            ->assertOk()
            ->assertJsonPath('message', 'E-commerce channel deleted successfully.');

        $this->apiGet('/ecommerce/channels/'.$this->channel([], $this->otherOrganization->id)->getRouteKey())->assertNotFound();
    }

    // ----------------------------------------------------------------
    // Orders
    // ----------------------------------------------------------------

    public function test_orders_are_listed_with_their_customer_by_reference(): void
    {
        $channel = $this->channel();
        $this->order($channel, ['customer_id' => $this->customer->id, 'order_number' => 'WEB-1001']);
        $this->order($this->channel([], $this->otherOrganization->id), [], $this->otherOrganization->id);

        $response = $this->apiGet('/ecommerce/orders?search=WEB-1001')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.channel.id', $channel->id);

        $this->assertSame(Contact::REFERENCE_COLUMNS, array_keys($response->json('data.0.customer')));

        $this->apiGet('/ecommerce/orders/stats')->assertOk()->assertJsonPath('data.total_orders', 1);
    }

    public function test_an_order_is_imported_into_this_organizations_channel(): void
    {
        $channel = $this->channel();

        $this->apiPost('/ecommerce/orders/import', $this->importPayload($channel->id))
            ->assertCreated()
            ->assertJsonPath('message', 'Order imported successfully.')
            ->assertJsonPath('data.channel_id', $channel->id)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_an_order_import_refuses_a_channel_or_product_of_another_organization(): void
    {
        $foreignChannel = $this->channel([], $this->otherOrganization->id);
        $foreignProduct = Product::factory()->create(['organization_id' => $this->otherOrganization->id]);

        $this->apiPost('/ecommerce/orders/import', $this->importPayload($foreignChannel->id))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $payload = $this->importPayload($this->channel()->id);
        $payload['items'][0]['product_id'] = $foreignProduct->id;

        $this->apiPost('/ecommerce/orders/import', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, DB::table('ecommerce_orders')->count());
    }

    // ----------------------------------------------------------------
    // Gateways and payments
    // ----------------------------------------------------------------

    public function test_a_new_default_gateway_becomes_the_only_default_and_hides_its_credentials(): void
    {
        $previous = PaymentGateway::factory()->create(['organization_id' => $this->organization->id, 'is_default' => true]);

        $response = $this->apiPost('/ecommerce/payment-gateways', [
            'name' => 'Moyasar',
            'provider' => 'moyasar',
            'mode' => 'test',
            'is_default' => true,
            'credentials' => ['secret_key' => 'sk_test_hidden'],
        ])->assertCreated()
            ->assertJsonPath('message', 'Payment gateway created successfully.');

        $this->assertStringNotContainsString('sk_test_hidden', $response->getContent());
        $this->assertFalse($previous->fresh()->is_default);

        $this->apiGet('/ecommerce/payment-gateways')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_gateway_with_payments_is_kept_and_one_without_is_deleted(): void
    {
        $used = PaymentGateway::factory()->create(['organization_id' => $this->organization->id]);
        $this->payment($used);
        $unused = PaymentGateway::factory()->create(['organization_id' => $this->organization->id]);

        $this->apiDelete('/ecommerce/payment-gateways/'.$used->getRouteKey())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->apiDelete('/ecommerce/payment-gateways/'.$unused->getRouteKey())
            ->assertOk()
            ->assertJsonPath('message', 'Payment gateway deleted successfully.');
    }

    public function test_payments_are_listed_and_shown_with_their_invoice_by_reference(): void
    {
        $gateway = PaymentGateway::factory()->create(['organization_id' => $this->organization->id]);
        $invoice = Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id]);
        $payment = $this->payment($gateway, ['payable_id' => $invoice->id]);

        $this->apiGet('/ecommerce/payments?status='.OnlinePayment::STATUS_CAPTURED)->assertOk()->assertJsonCount(1, 'data');

        $shown = $this->apiGet('/ecommerce/payments/'.$payment->getRouteKey())
            ->assertOk()
            ->assertJsonPath('data.gateway.id', $gateway->id);

        $this->assertSame(Invoice::REFERENCE_COLUMNS, array_keys($shown->json('data.payable')));
    }

    public function test_a_captured_payment_is_refunded(): void
    {
        $payment = $this->payment(PaymentGateway::factory()->create(['organization_id' => $this->organization->id]));

        $this->apiPost('/ecommerce/payments/'.$payment->getRouteKey().'/refund', ['reason' => 'Returned'])
            ->assertOk()
            ->assertJsonPath('message', 'Payment refunded successfully.')
            ->assertJsonPath('data.status', OnlinePayment::STATUS_REFUNDED);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function channel(array $attributes = [], ?int $organizationId = null): EcommerceChannel
    {
        return EcommerceChannel::factory()->create(array_merge([
            'organization_id' => $organizationId ?? $this->organization->id,
            'platform' => 'shopify',
            'status' => EcommerceChannel::STATUS_ACTIVE,
        ], $attributes));
    }

    private function order(EcommerceChannel $channel, array $attributes = [], ?int $organizationId = null): EcommerceOrder
    {
        return EcommerceOrder::factory()->create(array_merge([
            'organization_id' => $organizationId ?? $this->organization->id,
            'channel_id' => $channel->id,
            'ordered_at' => now(),
        ], $attributes));
    }

    private function payment(PaymentGateway $gateway, array $attributes = []): OnlinePayment
    {
        return OnlinePayment::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'gateway_id' => $gateway->id,
            'status' => OnlinePayment::STATUS_CAPTURED,
            'amount' => 100,
            'net_amount' => 100,
            'fee_amount' => 0,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function importPayload(int $channelId): array
    {
        return [
            'channel_id' => $channelId,
            'external_order_id' => 'EXT-5001',
            'order_number' => 'WEB-5001',
            'currency_code' => 'SAR',
            'subtotal' => 100,
            'total_amount' => 115,
            'ordered_at' => '2026-03-10 10:00:00',
            'items' => [
                ['name' => 'Mug', 'quantity' => 2, 'unit_price' => 50, 'total_amount' => 100],
            ],
        ];
    }
}
