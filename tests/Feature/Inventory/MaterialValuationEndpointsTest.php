<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Accounting\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Pins the variance report and keeps revaluation to the caller's products.
 */
class MaterialValuationEndpointsTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'inventory.products.view',
            'inventory.products.edit',
        ]);
    }

    public function test_the_variance_report_lists_variance_and_revaluation_entries_in_the_range(): void
    {
        $variance = $this->entry($this->organization->id, 'PPV-1', '2025-01-10');
        $revaluation = $this->entry($this->organization->id, 'REVAL-2', '2025-01-20');
        $this->entry($this->organization->id, 'INV-3', '2025-01-15');
        $this->entry($this->organization->id, 'PPV-4', '2025-03-01');
        $this->entry($this->otherOrganization()->id, 'PPV-5', '2025-01-12');

        $response = $this->apiGet('/inventory/valuation/variance-report?from=2025-01-01&to=2025-01-31');

        $response->assertOk();
        $this->assertSame([$revaluation->id, $variance->id], array_column($response->json('data.entries'), 'id'));
        $this->assertSame(['revaluation', 'price_variance'], array_column($response->json('data.entries'), 'type'));
        $this->assertSame(2, $response->json('data.meta.total'));
    }

    public function test_another_organizations_product_cannot_be_revalued(): void
    {
        $response = $this->apiPost('/inventory/valuation/revalue', [
            'product_id' => $this->foreignProduct()->id,
            'new_unit_cost' => 9,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['product_id']);
    }

    private function entry(int $organizationId, string $reference, string $date): JournalEntry
    {
        return JournalEntry::withoutGlobalScopes()->forceCreate([
            'organization_id' => $organizationId,
            'entry_number' => 'JE-'.fake()->unique()->numerify('#####'),
            'entry_date' => $date,
            'reference' => $reference,
            'description' => $reference,
            'currency_code' => 'SAR',
            'status' => JournalEntry::STATUS_POSTED,
        ]);
    }
}
