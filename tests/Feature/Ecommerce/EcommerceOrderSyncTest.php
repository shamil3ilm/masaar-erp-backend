<?php

declare(strict_types=1);

namespace Tests\Feature\Ecommerce;

use App\Models\Ecommerce\EcommerceChannel;
use App\Models\Ecommerce\EcommerceOrder;
use App\Services\Ecommerce\EcommerceOrderService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * An external order is imported once per channel. When two syncs pull the same
 * order at the same time, the one that finds it already imported updates it
 * instead of failing.
 */
class EcommerceOrderSyncTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_an_order_imported_by_an_overlapping_sync_is_updated_not_failed(): void
    {
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');

        $channel = EcommerceChannel::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => EcommerceChannel::STATUS_ACTIVE,
        ]);

        $interleaved = false;

        // Stands in for an overlapping sync that imports the same order after
        // this sync has looked for it and before this sync inserts it.
        DB::listen(function (QueryExecuted $query) use ($channel, &$interleaved): void {
            if ($interleaved || ! str_starts_with(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'ecommerce_orders')) {
                return;
            }

            $interleaved = true;

            DB::table('ecommerce_orders')->insert([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $channel->organization_id,
                'channel_id' => $channel->id,
                'external_order_id' => 'EXT-1001',
                'order_number' => '#1001',
                'status' => EcommerceOrder::STATUS_PENDING,
                'currency_code' => 'SAR',
                'subtotal' => 100,
                'total_amount' => 100,
                'ordered_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $sync = new class extends EcommerceOrderService
        {
            public function sync(EcommerceChannel $channel, array $externalOrder): EcommerceOrder
            {
                return $this->syncSingleOrder($channel, $externalOrder);
            }
        };

        $order = $sync->sync($channel, [
            'external_order_id' => 'EXT-1001',
            'order_number' => '#1001',
            'status' => EcommerceOrder::STATUS_PROCESSING,
            'currency_code' => 'SAR',
            'subtotal' => 100,
            'total_amount' => 100,
            'ordered_at' => now(),
            'items' => [],
        ]);

        $this->assertSame(1, EcommerceOrder::where('external_order_id', 'EXT-1001')->count());
        $this->assertSame(EcommerceOrder::STATUS_PROCESSING, $order->status);
    }
}
