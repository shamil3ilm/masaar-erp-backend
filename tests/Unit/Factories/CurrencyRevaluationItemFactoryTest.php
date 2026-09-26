<?php

declare(strict_types=1);

namespace Tests\Unit\Factories;

use App\Models\Accounting\Currency;
use App\Models\Accounting\CurrencyRevaluationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CurrencyRevaluationItem::factory() writes a row.
 *
 * The factory gave null to account_id, and the revaluation it belongs to gave
 * null to created_by; both columns are foreign keys their tables require.
 * make() builds such a model happily and create() fails on the constraint, so
 * a test that wanted an item had to assemble one by hand instead of asking
 * for one.
 *
 * The currencies are seeded here because an account's currency_code is itself
 * a key into that table, and this test starts from an empty database rather
 * than the seeded one the accounting tests run against.
 */
class CurrencyRevaluationItemFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_writes_an_item_with_the_rows_its_keys_require(): void
    {
        foreach (['SAR', 'AED', 'INR', 'USD'] as $code) {
            Currency::firstOrCreate(['code' => $code], ['name' => $code, 'symbol' => $code]);
        }

        $item = CurrencyRevaluationItem::factory()->create();

        $this->assertNotNull($item->account_id);
        $this->assertNotNull($item->revaluation->created_by);
        $this->assertDatabaseHas('currency_revaluation_items', ['id' => $item->id]);
    }
}
