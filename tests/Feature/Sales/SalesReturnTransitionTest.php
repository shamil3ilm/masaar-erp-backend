<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Exceptions\ApiException;
use App\Models\Accounting\Account;
use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\Sales\CreditNote;
use App\Models\Sales\ExchangeOrder;
use App\Models\Sales\ReturnPolicy;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Services\Sales\SalesReturnService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Resolving a return, creating one and exchanging one are each all or
 * nothing: a failed credit note, restock, restocking fee or exchange item
 * fails the whole change, and a resolution or exchange made through a stale
 * copy of the return is rejected.
 */
class SalesReturnTransitionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private SalesReturnService $service;
    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['sales.returns.view', 'sales.returns.resolve']);
        $this->actingAs($this->user);
        $this->setUpOpenFiscalPeriod();

        foreach ([
            ['1200', 'Accounts Receivable', Account::TYPE_ASSET, Account::SUBTYPE_RECEIVABLE],
            ['4100', 'Sales Returns', Account::TYPE_INCOME, Account::SUBTYPE_SALES],
        ] as [$code, $name, $type, $subType]) {
            Account::factory()->create([
                'organization_id' => $this->organization->id,
                'account_type' => $type,
                'sub_type' => $subType,
                'code' => $code,
                'name' => $name,
                'is_system' => true,
                'currency_code' => null,
            ]);
        }

        $this->customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
            'currency_code' => 'SAR',
        ]);

        $this->service = app(SalesReturnService::class);
    }

    public function test_a_second_resolution_on_a_stale_return_is_rejected(): void
    {
        $return = $this->inspectedReturn();
        $stale = SalesReturn::findOrFail($return->id);

        $this->service->resolve($return, SalesReturn::RESOLUTION_CREDIT_NOTE, $this->user->id);

        $this->assertApiRejected(fn () => $this->service->resolve($stale, SalesReturn::RESOLUTION_CREDIT_NOTE, $this->user->id));
        $this->assertSame(1, CreditNote::count());
        $this->assertSame(SalesReturn::STATUS_COMPLETED, $return->fresh()->status);
    }

    public function test_resolution_rolls_back_when_the_credit_note_cannot_be_created(): void
    {
        $return = $this->inspectedReturn();
        CreditNote::creating(function (): void {
            throw new \RuntimeException('credit note store unavailable');
        });

        $this->assertFailsWith('credit note store unavailable',
            fn () => $this->service->resolve($return, SalesReturn::RESOLUTION_CREDIT_NOTE, $this->user->id));

        $fresh = $return->fresh();
        $this->assertSame(SalesReturn::STATUS_INSPECTED, $fresh->status);
        $this->assertNull($fresh->credit_note_id);
    }

    public function test_resolution_rolls_back_when_restocking_fails(): void
    {
        $return = $this->inspectedReturn(restock: true);
        SalesReturnItem::updating(function (): void {
            throw new \RuntimeException('restock failed');
        });

        $this->assertFailsWith('restock failed',
            fn () => $this->service->resolve($return, SalesReturn::RESOLUTION_EXCHANGE, $this->user->id));

        $this->assertSame(SalesReturn::STATUS_INSPECTED, $return->fresh()->status);
    }

    public function test_create_applies_the_default_policy_restocking_fee(): void
    {
        $this->defaultPolicy(restockingFeePercent: 10);

        $return = $this->service->create($this->returnData(), $this->user->id);

        $this->assertSame(0, bccomp((string) $return->restocking_fee, '10', 2), "restocking_fee is {$return->restocking_fee}");
        $this->assertSame(0, bccomp((string) $return->total, '90', 2), "total is {$return->total}");
    }

    public function test_create_rolls_back_when_the_restocking_fee_cannot_be_applied(): void
    {
        $this->defaultPolicy(restockingFeePercent: 10);
        SalesReturn::updating(function (SalesReturn $return): void {
            if ($return->isDirty('restocking_fee')) {
                throw new \RuntimeException('fee not saved');
            }
        });

        $this->assertFailsWith('fee not saved', fn () => $this->service->create($this->returnData(), $this->user->id));

        $this->assertSame(0, SalesReturn::count());
    }

    public function test_exchange_rolls_back_when_an_item_cannot_be_created(): void
    {
        $return = $this->inspectedReturn();

        try {
            $this->service->createExchange($return, [
                ['replacement_quantity' => 1, 'replacement_unit_price' => 100],
            ], $this->user->id);
            $this->fail('An exchange item that cannot be saved must fail the exchange.');
        } catch (QueryException) {
        }

        $this->assertSame(0, ExchangeOrder::count());
        $this->assertNull($return->fresh()->exchange_order_id);
    }

    public function test_a_second_exchange_on_a_stale_return_is_rejected(): void
    {
        $return = $this->inspectedReturn();
        $stale = SalesReturn::findOrFail($return->id);
        $items = [$this->exchangeItem()];

        $this->service->createExchange($return, $items, $this->user->id);

        $this->assertApiRejected(fn () => $this->service->createExchange($stale, $items, $this->user->id));
        $this->assertSame(1, ExchangeOrder::count());
    }

    private function assertApiRejected(callable $action): void
    {
        try {
            $action();
        } catch (ApiException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('The transition was expected to be rejected.');
    }

    private function assertFailsWith(string $message, callable $action): void
    {
        try {
            $action();
        } catch (\RuntimeException $e) {
            $this->assertSame($message, $e->getMessage());

            return;
        }

        $this->fail("Expected the change to fail with: {$message}");
    }

    private function inspectedReturn(bool $restock = false): SalesReturn
    {
        $return = SalesReturn::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'currency_code' => 'SAR',
            'status' => SalesReturn::STATUS_INSPECTED,
            'restock_items' => $restock,
            'subtotal' => 100,
            'tax_amount' => 0,
            'restocking_fee' => 0,
            'total' => 100,
            'refund_amount' => 0,
        ]);

        SalesReturnItem::factory()->create([
            'sales_return_id' => $return->id,
            'description' => 'Returned chair',
            'quantity_returned' => 1,
            'quantity_received' => 1,
            'unit_price' => 100,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'subtotal' => 100,
            'total' => 100,
            'condition' => SalesReturnItem::CONDITION_NEW,
        ]);

        return $return;
    }

    private function returnData(): array
    {
        return [
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'return_date' => now()->toDateString(),
            'return_type' => SalesReturn::TYPE_REFUND,
            'currency_code' => 'SAR',
            'reason_notes' => 'Wrong size',
            'items' => [
                ['description' => 'Chair', 'quantity_returned' => 2, 'unit_price' => 50, 'tax_rate' => 0],
            ],
        ];
    }

    private function defaultPolicy(float $restockingFeePercent): ReturnPolicy
    {
        return ReturnPolicy::create([
            'organization_id' => $this->organization->id,
            'name' => 'Default',
            'return_window_days' => 30,
            'restocking_fee_percent' => $restockingFeePercent,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function exchangeItem(): array
    {
        [$original, $replacement] = Product::factory()->service()->count(2)->create([
            'organization_id' => $this->organization->id,
            'track_inventory' => false,
        ]);

        return [
            'original_product_id' => $original->id,
            'replacement_product_id' => $replacement->id,
            'original_quantity' => 1,
            'replacement_quantity' => 1,
            'original_unit_price' => 100,
            'replacement_unit_price' => 100,
        ];
    }
}
