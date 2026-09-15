<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\DocumentDownloadToken;
use App\Models\Core\Organization;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the document download link endpoints: a link is issued only for a
 * document of the caller's organization, and a download needs a known token
 * that has not expired.
 */
class DocumentDownloadEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['documents.files.view', 'documents.files.manage']);
    }

    public function test_a_link_is_issued_only_for_the_organizations_document(): void
    {
        $own = Invoice::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = Invoice::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $token = $this->apiPost('/documents/generate-link', ['document_id' => $own->id, 'document_type' => 'invoice'])
            ->assertOk()
            ->assertJsonPath('message', 'Download link generated successfully.')
            ->json('data.token');
        $this->assertTrue(DocumentDownloadToken::where('token', $token)->where('document_id', $own->id)->exists());

        $this->apiPost('/documents/generate-link', ['document_id' => $foreign->id, 'document_type' => 'invoice'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DOCUMENT_NOT_FOUND');
    }

    public function test_an_unknown_or_expired_token_does_not_download(): void
    {
        $this->apiGet('/documents/download/'.str_repeat('a', 40))
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Download link not found.');

        $expired = DocumentDownloadToken::create([
            'document_id' => 1,
            'document_type' => DocumentDownloadToken::TYPE_INVOICE,
            'organization_id' => $this->organization->id,
            'token' => str_repeat('b', 40),
            'expires_at' => now()->subHour(),
        ]);

        $this->apiGet("/documents/download/{$expired->token}")
            ->assertStatus(410)
            ->assertJsonPath('error.message', 'This download link has expired.');
    }
}
