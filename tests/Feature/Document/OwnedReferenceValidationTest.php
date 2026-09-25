<?php

declare(strict_types=1);

namespace Tests\Feature\Document;

use App\Models\Core\Organization;
use App\Models\Document\Document;
use App\Models\Document\DocumentFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Document endpoints refuse a folder or signer of another organization.
 *
 * Each case sends the same request twice: once with a row of a second
 * organization, which the field must reject, and once with the caller's own
 * row, which the field must accept. The second call only asserts that the
 * field itself passed validation, so a case stays about the rule rather than
 * about whatever the endpoint does afterwards.
 */
class OwnedReferenceValidationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'documents.files.view',
            'documents.files.create',
            'documents.files.manage',
            'documents.signatures.manage',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_an_upload_is_not_filed_in_another_organizations_folder(): void
    {
        // The upload itself is left out: the folder rule is checked with the
        // rest of the rules, so the missing file does not hide its verdict.
        $this->apiPost('/documents', ['folder_id' => $this->folder($this->otherOrg)->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('folder_id');

        $this->apiPost('/documents', ['folder_id' => $this->folder()->id])
            ->assertUnprocessable()
            ->assertJsonMissingValidationErrors('folder_id');
    }

    public function test_a_document_is_not_moved_into_another_organizations_folder(): void
    {
        $url = "/documents/{$this->document()->id}/move";

        $this->apiPost($url, ['folder_id' => $this->folder($this->otherOrg)->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('folder_id');

        $this->apiPost($url, ['folder_id' => $this->folder()->id])
            ->assertJsonMissingValidationErrors('folder_id');
    }

    public function test_a_signature_is_not_requested_from_another_organizations_user(): void
    {
        $url = "/documents/{$this->document()->id}/signatures";

        $this->apiPost($url, $this->signaturePayload(
            User::factory()->create(['organization_id' => $this->otherOrg->id])->id
        ))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('signer_id');

        $this->apiPost($url, $this->signaturePayload($this->user->id))
            ->assertJsonMissingValidationErrors('signer_id');
    }

    /** @return array<string, mixed> */
    private function signaturePayload(int $signerId): array
    {
        return [
            'signer_email' => 'signer@example.test',
            'signer_name' => 'Nadia Salim',
            'signer_id' => $signerId,
        ];
    }

    private function folder(?Organization $organization = null): DocumentFolder
    {
        $organization ??= $this->organization;

        return DocumentFolder::factory()->create([
            'organization_id' => $organization->id,
            'created_by' => $organization->is($this->organization)
                ? $this->user->id
                : User::factory()->create(['organization_id' => $organization->id])->id,
        ]);
    }

    private function document(): Document
    {
        return Document::factory()->create([
            'organization_id' => $this->organization->id,
            'uploaded_by' => $this->user->id,
        ]);
    }
}
