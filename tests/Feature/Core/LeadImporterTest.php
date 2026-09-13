<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\ImportJob;
use App\Models\CRM\LeadSource;
use App\Services\Core\Importers\LeadImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The lead import could not create a lead.
 *
 * It wrote a free-text "source" the model does not accept - leads point at a
 * lead source instead - and a null contact name into a column that requires
 * one.
 */
class LeadImporterTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private ImportJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->job = ImportJob::factory()->create([
            'organization_id' => $this->organization->id,
            'entity_type' => ImportJob::ENTITY_LEADS,
        ]);
    }

    public function test_a_named_source_becomes_a_lead_source_shared_by_later_rows(): void
    {
        $first = (new LeadImporter)->importRow([
            'company_name' => 'Alpha',
            'source' => 'Trade Show',
        ], $this->job);

        $second = (new LeadImporter)->importRow([
            'company_name' => 'Beta',
            'source' => 'Trade Show',
        ], $this->job);

        $this->assertNotNull($first->fresh()->lead_source_id);
        $this->assertSame($first->fresh()->lead_source_id, $second->fresh()->lead_source_id);
        $this->assertSame(1, LeadSource::where('organization_id', $this->organization->id)->count());
    }

    public function test_a_row_with_only_a_company_name_imports(): void
    {
        $lead = (new LeadImporter)->importRow([
            'company_name' => 'Gamma',
        ], $this->job);

        $fresh = $lead->fresh();
        $this->assertSame('Gamma', $fresh->contact_name);
        $this->assertSame('new', $fresh->status);
        $this->assertNull($fresh->lead_source_id);
        $this->assertSame(0, LeadSource::where('organization_id', $this->organization->id)->count());
    }

    public function test_a_status_the_column_does_not_allow_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid lead status');

        (new LeadImporter)->importRow([
            'company_name' => 'Delta',
            'status' => 'hot',
        ], $this->job);
    }
}
