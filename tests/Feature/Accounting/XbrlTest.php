<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\XbrlFiling;
use App\Models\Accounting\XbrlTaxonomy;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class XbrlTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.xbrl.view',
            'accounting.xbrl.manage',
            'accounting.xbrl.create',
            'accounting.xbrl.edit',
            'accounting.xbrl.submit',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTaxonomy(array $overrides = []): XbrlTaxonomy
    {
        return XbrlTaxonomy::create(array_merge([
            'organization_id' => $this->organization->id,
            'name'            => 'IFRS Taxonomy ' . fake()->unique()->numerify('###'),
            'version'         => '2023',
            'namespace'       => 'https://xbrl.ifrs.org/taxonomy/' . fake()->unique()->numerify('###'),
            'is_active'       => true,
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    private function makeFiscalYear(): FiscalYear
    {
        return FiscalYear::create([
            'organization_id' => $this->organization->id,
            'name'            => 'FY2025',
            'start_date'      => '2025-01-01',
            'end_date'        => '2025-12-31',
            'is_closed'       => false,
        ]);
    }

    private function makeFiling(XbrlTaxonomy $taxonomy, FiscalYear $fiscalYear, array $overrides = []): XbrlFiling
    {
        return XbrlFiling::create(array_merge([
            'organization_id' => $this->organization->id,
            'fiscal_year_id'  => $fiscalYear->id,
            'taxonomy_id'     => $taxonomy->id,
            'period_start'    => '2025-01-01',
            'period_end'      => '2025-12-31',
            'status'          => XbrlFiling::STATUS_DRAFT,
            'created_by'      => $this->user->id,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Taxonomies Index
    // -------------------------------------------------------------------------

    public function test_taxonomies_index_returns_list(): void
    {
        $this->makeTaxonomy();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/taxonomies');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Taxonomies Store
    // -------------------------------------------------------------------------

    public function test_taxonomies_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/taxonomies', []);

        $response->assertStatus(422);
    }

    public function test_taxonomies_store_creates_taxonomy(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/taxonomies', [
                'name'      => 'IFRS 2023',
                'version'   => '2023',
                'namespace' => 'https://xbrl.ifrs.org/taxonomy/2023',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Taxonomies Show/Update
    // -------------------------------------------------------------------------

    public function test_taxonomies_show_returns_details(): void
    {
        $taxonomy = $this->makeTaxonomy();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/taxonomies/' . $taxonomy->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_taxonomies_update_modifies_taxonomy(): void
    {
        $taxonomy = $this->makeTaxonomy(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/xbrl/taxonomies/' . $taxonomy->uuid, [
                'name' => 'Updated Taxonomy',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Taxonomy', $taxonomy->fresh()->name);
    }

    // -------------------------------------------------------------------------
    // Filings Index
    // -------------------------------------------------------------------------

    public function test_filings_index_returns_list(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/filings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Filings Store
    // -------------------------------------------------------------------------

    public function test_filings_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/filings', []);

        $response->assertStatus(422);
    }

    public function test_filings_store_creates_filing(): void
    {
        $taxonomy   = $this->makeTaxonomy();
        $fiscalYear = $this->makeFiscalYear();

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/filings', [
                'fiscal_year_id' => $fiscalYear->id,
                'taxonomy_id'    => $taxonomy->id,
                'report_type'    => 'annual',
                'period_start'   => '2025-01-01',
                'period_end'     => '2025-12-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // Filings Show
    // -------------------------------------------------------------------------

    public function test_filings_show_returns_details(): void
    {
        $taxonomy   = $this->makeTaxonomy();
        $fiscalYear = $this->makeFiscalYear();
        $filing     = $this->makeFiling($taxonomy, $fiscalYear);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/filings/' . $filing->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_filings_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/filings/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Validate
    // -------------------------------------------------------------------------

    public function test_validate_filing_returns_result(): void
    {
        $taxonomy   = $this->makeTaxonomy();
        $fiscalYear = $this->makeFiscalYear();
        $filing     = $this->makeFiling($taxonomy, $fiscalYear);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/filings/' . $filing->uuid . '/validate');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_taxonomies_index_filters_active_and_paginates(): void
    {
        $this->makeTaxonomy(['name' => 'B Taxonomy']);
        $this->makeTaxonomy(['name' => 'A Taxonomy']);
        $this->makeTaxonomy(['name' => 'C Taxonomy', 'is_active' => false]);

        $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/taxonomies?per_page=2')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'A Taxonomy')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);

        $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/taxonomies?active_only=1')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_taxonomies_update_returns_updated_taxonomy(): void
    {
        $taxonomy = $this->makeTaxonomy(['name' => 'Old Name']);

        $this->withToken($this->token)
            ->putJson('/api/v1/xbrl/taxonomies/' . $taxonomy->uuid, ['name' => 'Renamed', 'is_active' => false])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Taxonomy updated.')
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_filings_index_filters_by_status_and_fiscal_year(): void
    {
        $taxonomy   = $this->makeTaxonomy();
        $fiscalYear = $this->makeFiscalYear();
        $this->makeFiling($taxonomy, $fiscalYear);
        $validated  = $this->makeFiling($taxonomy, $fiscalYear, ['status' => XbrlFiling::STATUS_VALIDATED]);

        $this->withToken($this->token)
            ->getJson('/api/v1/xbrl/filings?status=validated&fiscal_year_id=' . $fiscalYear->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $validated->id)
            ->assertJsonPath('data.0.taxonomy.name', $taxonomy->name)
            ->assertJsonPath('data.0.fiscal_year.name', 'FY2025')
            ->assertJsonPath('data.0.created_by.id', $this->user->id)
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_filings_store_returns_404_for_another_organizations_fiscal_year_or_taxonomy(): void
    {
        $otherOrg      = Organization::factory()->create();
        $taxonomy      = $this->makeTaxonomy();
        $fiscalYear    = $this->makeFiscalYear();
        $otherTaxonomy = $this->makeTaxonomy(['organization_id' => $otherOrg->id]);
        $otherYear     = FiscalYear::create([
            'organization_id' => $otherOrg->id,
            'name'            => 'FY2025 other',
            'start_date'      => '2025-01-01',
            'end_date'        => '2025-12-31',
            'is_closed'       => false,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/filings', ['fiscal_year_id' => $otherYear->id, 'taxonomy_id' => $taxonomy->id])
            ->assertStatus(404);

        $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/filings', ['fiscal_year_id' => $fiscalYear->id, 'taxonomy_id' => $otherTaxonomy->id])
            ->assertStatus(404);

        $this->assertSame(0, XbrlFiling::withoutGlobalScopes()->count());
    }

    public function test_filings_store_returns_created_filing_with_relations(): void
    {
        $taxonomy   = $this->makeTaxonomy();
        $fiscalYear = $this->makeFiscalYear();

        $this->withToken($this->token)
            ->postJson('/api/v1/xbrl/filings', ['fiscal_year_id' => $fiscalYear->id, 'taxonomy_id' => $taxonomy->id])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Filing created successfully.')
            ->assertJsonPath('data.status', XbrlFiling::STATUS_DRAFT)
            ->assertJsonPath('data.taxonomy.id', $taxonomy->id)
            ->assertJsonPath('data.fiscal_year.id', $fiscalYear->id)
            ->assertJsonPath('data.elements', []);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/xbrl/taxonomies')->assertStatus(401);
    }
}
