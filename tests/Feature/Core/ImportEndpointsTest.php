<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\ImportJob;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the import job endpoints around their state rules: an import is found
 * only in the caller's organization, configured and previewed only while
 * pending, processed only once configured and not yet started, and cancelled
 * only when it is not being processed.
 */
class ImportEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.imports.view', 'core.imports.manage']);
        Storage::fake('local');
    }

    public function test_an_import_is_found_only_in_the_organization(): void
    {
        $own = $this->importJob($this->organization->id, ImportJob::STATUS_PENDING);
        $foreign = $this->importJob(Organization::factory()->create()->id, ImportJob::STATUS_PENDING);

        $this->apiGet("/imports/{$own->uuid}")->assertOk();

        $this->apiGet("/imports/{$foreign->uuid}")->assertNotFound();
        $this->apiGet("/imports/{$foreign->uuid}/preview")->assertNotFound();
        $this->apiPost("/imports/{$foreign->uuid}/configure", ['column_mapping' => ['a' => 'b']])->assertNotFound();
        $this->apiPost("/imports/{$foreign->uuid}/process")->assertNotFound();
        $this->apiPost("/imports/{$foreign->uuid}/cancel")->assertNotFound();
        $this->assertSame(ImportJob::STATUS_PENDING, ImportJob::withoutGlobalScopes()->findOrFail($foreign->id)->status);
    }

    public function test_a_finished_import_is_not_configured_previewed_or_processed_again(): void
    {
        $done = $this->importJob($this->organization->id, ImportJob::STATUS_COMPLETED, ['column_mapping' => ['a' => 'b']]);

        $this->apiPost("/imports/{$done->uuid}/configure", ['column_mapping' => ['c' => 'd']])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_STATE')
            ->assertJsonPath('error.message', 'Import has already been processed');
        $this->apiGet("/imports/{$done->uuid}/preview")->assertStatus(400)->assertJsonPath('error.code', 'INVALID_STATE');
        $this->apiPost("/imports/{$done->uuid}/process")
            ->assertStatus(400)
            ->assertJsonPath('error.message', 'Import cannot be processed in current state');

        $this->assertSame(['a' => 'b'], $done->fresh()->column_mapping);
    }

    public function test_an_unconfigured_import_is_not_processed(): void
    {
        $pending = $this->importJob($this->organization->id, ImportJob::STATUS_PENDING);

        $this->apiPost("/imports/{$pending->uuid}/process")
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'CONFIGURATION_REQUIRED');
        $this->assertSame(ImportJob::STATUS_PENDING, $pending->fresh()->status);
    }

    public function test_an_import_is_cancelled_unless_it_is_processing(): void
    {
        $processing = $this->importJob($this->organization->id, ImportJob::STATUS_PROCESSING);
        $pending = $this->importJob($this->organization->id, ImportJob::STATUS_PENDING);

        $this->apiPost("/imports/{$processing->uuid}/cancel")
            ->assertStatus(400)
            ->assertJsonPath('error.message', 'Cannot cancel import in current state');

        $this->apiPost("/imports/{$pending->uuid}/cancel")
            ->assertOk()
            ->assertJsonPath('message', 'Import cancelled successfully');
        $this->assertSame(ImportJob::STATUS_CANCELLED, $pending->fresh()->status);
        $this->assertSame(ImportJob::STATUS_PROCESSING, $processing->fresh()->status);
    }

    public function test_an_upload_refuses_an_unknown_entity_type(): void
    {
        $this->post('/api/v1/imports/upload', [
            'file' => UploadedFile::fake()->create('rows.csv', 1, 'text/csv'),
            'entity_type' => 'no-such-type',
        ], $this->authHeaders())
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_ENTITY_TYPE');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function importJob(int $organizationId, string $status, array $attributes = []): ImportJob
    {
        return ImportJob::withoutGlobalScopes()->forceCreate(array_merge([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'user_id' => $this->user->id,
            'entity_type' => array_key_first(ImportJob::getEntityTypes()),
            'file_name' => 'rows.csv',
            'file_path' => 'imports/rows.csv',
            'original_name' => 'rows.csv',
            'file_size' => 1,
            'status' => $status,
        ], $attributes));
    }
}
