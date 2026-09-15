<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\ExportJob;
use App\Models\Core\Organization;
use App\Models\Core\OrganizationModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the export endpoints. An export asks only for the columns its entity
 * type offers, so a request cannot pull other attributes, such as an
 * employee's decrypted identity or bank numbers, into the file. A quick export
 * needs the entity's module enabled, as a regular export does.
 */
class ExportEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.exports.view', 'core.exports.manage']);
    }

    public function test_an_export_refuses_columns_its_entity_type_does_not_offer(): void
    {
        foreach (['/exports', '/exports/quick'] as $uri) {
            $this->apiPost($uri, ['entity_type' => 'employees', 'columns' => ['first_name', 'national_id']])
                ->assertStatus(422)
                ->assertJsonValidationErrors('columns.1');
        }

        $this->assertSame(0, ExportJob::count());
    }

    public function test_a_quick_export_needs_the_entity_module_enabled(): void
    {
        OrganizationModule::where('organization_id', $this->organization->id)
            ->where('module_code', 'hr')
            ->update(['is_enabled' => false]);

        $this->apiPost('/exports/quick', ['entity_type' => 'employees'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MODULE_DISABLED');
        $this->apiPost('/exports', ['entity_type' => 'employees'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MODULE_DISABLED');

        $this->assertSame(0, ExportJob::count());
    }

    public function test_an_export_status_and_download_are_found_only_in_the_organization(): void
    {
        $own = $this->exportJob($this->organization->id);
        $foreign = $this->exportJob(Organization::factory()->create()->id);

        $this->apiGet("/exports/{$own->uuid}")->assertOk();
        $this->apiGet("/exports/{$own->uuid}/download")
            ->assertStatus(400)
            ->assertJsonPath('error.message', 'Export is not ready for download.');

        $this->apiGet("/exports/{$foreign->uuid}")->assertNotFound();
        $this->apiGet("/exports/{$foreign->uuid}/download")->assertNotFound();
    }

    private function exportJob(int $organizationId): ExportJob
    {
        return ExportJob::withoutGlobalScopes()->forceCreate([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'user_id' => $this->user->id,
            'entity_type' => 'employees',
            'format' => 'xlsx',
            'status' => ExportJob::STATUS_PENDING,
        ]);
    }
}
