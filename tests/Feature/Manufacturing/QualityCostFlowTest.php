<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\QualityCostEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Quality cost entries are reached only within the organization and accept
 * only its own products and users; the trend sums each month's entries.
 */
class QualityCostFlowTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.manage',
        ]);
    }

    public function test_another_organizations_product_and_user_are_refused(): void
    {
        $this->apiPost('/manufacturing/quality-costs', [
            'cost_category' => QualityCostEntry::CATEGORY_APPRAISAL,
            'period' => 1,
            'fiscal_year' => 2026,
            'amount' => 10,
            'product_id' => $this->foreignProduct()->id,
            'recorded_by' => $this->foreignUser()->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['product_id', 'recorded_by']);

        $entry = $this->entry(QualityCostEntry::CATEGORY_APPRAISAL, 10);

        $this->apiPut("/manufacturing/quality-costs/{$entry->id}", ['product_id' => $this->foreignProduct()->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_an_entry_is_shown_updated_and_deleted_with_its_product(): void
    {
        $product = $this->stockedProduct();
        $entry = $this->entry(QualityCostEntry::CATEGORY_PREVENTION, 10, ['product_id' => $product->id]);

        $this->apiGet("/manufacturing/quality-costs/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('data.product.id', $product->id);

        $this->apiPut("/manufacturing/quality-costs/{$entry->id}", ['amount' => 25])
            ->assertOk()
            ->assertJsonPath('data.amount', '25.0000');

        $this->apiDelete("/manufacturing/quality-costs/{$entry->id}")->assertOk();

        $this->assertSoftDeleted('quality_cost_entries', ['id' => $entry->id]);
    }

    public function test_another_organizations_entry_is_not_found(): void
    {
        $theirs = QualityCostEntry::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $this->apiGet("/manufacturing/quality-costs/{$theirs->id}")->assertNotFound();
        $this->apiPut("/manufacturing/quality-costs/{$theirs->id}", ['amount' => 1])->assertNotFound();
        $this->apiDelete("/manufacturing/quality-costs/{$theirs->id}")->assertNotFound();

        $this->assertNotSoftDeleted('quality_cost_entries', ['id' => $theirs->id]);
    }

    public function test_the_trend_sums_each_months_entries_by_category(): void
    {
        $months = array_map(fn (int $ago) => now()->subMonths($ago), [3, 2, 1, 0]);
        [$first, , , $current] = $months;
        $before = now()->subMonths(4);

        $this->entry(QualityCostEntry::CATEGORY_APPRAISAL, 100, $this->periodOf($current));
        $this->entry(QualityCostEntry::CATEGORY_APPRAISAL, 50, $this->periodOf($current));
        $this->entry(QualityCostEntry::CATEGORY_PREVENTION, 20, $this->periodOf($first));
        $this->entry(QualityCostEntry::CATEGORY_PREVENTION, 999, $this->periodOf($before));
        QualityCostEntry::factory()->create([
            'organization_id' => $this->otherOrganization()->id,
            'cost_category' => QualityCostEntry::CATEGORY_APPRAISAL,
            'amount' => 7000,
            ...$this->periodOf($current),
        ]);

        $trend = $this->apiGet('/manufacturing/quality-costs/trend?months=4')->assertOk()->json('data');

        $this->assertSame(array_map(fn ($date) => $date->format('M Y'), $months), array_column($trend, 'label'));
        $this->assertSame(array_map(fn ($date) => (int) $date->format('n'), $months), array_column($trend, 'period'));
        $this->assertEqualsWithDelta(20.0, (float) $trend[0]['prevention'], 0.0001);
        $this->assertSame('0.0000', $trend[1]['appraisal']);
        $this->assertEqualsWithDelta(150.0, (float) $trend[3]['appraisal'], 0.0001);
        $this->assertSame('0.0000', $trend[3]['prevention']);
    }

    private function periodOf(\DateTimeInterface $date): array
    {
        return ['period' => (int) $date->format('n'), 'fiscal_year' => (int) $date->format('Y')];
    }

    private function entry(string $category, float $amount, array $overrides = []): QualityCostEntry
    {
        return QualityCostEntry::create(array_merge([
            'organization_id' => $this->organization->id,
            'cost_category' => $category,
            'period' => 1,
            'fiscal_year' => 2026,
            'amount' => $amount,
        ], $overrides));
    }
}
