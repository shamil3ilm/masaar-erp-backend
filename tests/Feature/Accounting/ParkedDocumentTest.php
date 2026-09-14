<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Exceptions\ERP\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\ParkedDocument;
use App\Services\Accounting\ParkedDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ParkedDocumentTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.parked-documents.manage',
            'accounting.parked-documents.view',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeDocument(array $overrides = []): ParkedDocument
    {
        return ParkedDocument::create(array_merge([
            'organization_id' => $this->organization->id,
            'document_type'   => 'vendor_invoice',
            'document_date'   => '2025-01-15',
            'posting_date'    => '2025-01-15',
            'document_data'   => ['lines' => []],
            'total_debit'     => 1000.00,
            'total_credit'    => 1000.00,
            'currency_code'   => 'SAR',
            'status'          => ParkedDocument::STATUS_PARKED,
            'parked_by'       => $this->user->id,
        ], $overrides));
    }

    /**
     * A fiscal year for 2025 and a balanced pair of lines on two own accounts.
     *
     * @return list<array<string, mixed>>
     */
    private function postableLines(): array
    {
        FiscalYear::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'name'            => 'FY 2025',
            'start_date'      => '2025-01-01',
            'end_date'        => '2025-12-31',
            'is_current'      => true,
            'is_closed'       => false,
        ]);

        $account = fn (string $code, string $type, string $subType): Account => Account::withoutGlobalScopes()->create([
            'organization_id' => $this->organization->id,
            'code'            => $code,
            'name'            => "Account {$code}",
            'account_type'    => $type,
            'sub_type'        => $subType,
            'currency_code'   => 'SAR',
            'is_active'       => true,
            'is_system'       => false,
            'is_header'       => false,
            'level'           => 1,
        ]);

        return [
            ['account_id' => $account('1001', Account::TYPE_ASSET, Account::SUBTYPE_CASH)->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $account('4001', Account::TYPE_INCOME, Account::SUBTYPE_SALES)->id, 'debit' => 0, 'credit' => 1000],
        ];
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_index_applies_filters_newest_first_with_parker(): void
    {
        $january = $this->makeDocument(['document_date' => '2025-01-15']);
        $march = $this->makeDocument(['document_date' => '2025-03-01', 'document_type' => 'payment']);
        $this->makeDocument(['document_date' => '2025-02-01', 'status' => ParkedDocument::STATUS_POSTED]);

        $ids = fn (string $query): array => array_column(
            $this->withToken($this->token)->getJson("/api/v1/parked-documents?{$query}")->json('data'),
            'id'
        );

        $this->assertSame([$march->id, $january->id], $ids('status=parked'));
        $this->assertSame([$march->id], $ids('document_type=payment'));
        $this->assertSame([$march->id], $ids('date_from=2025-02-15'));
        $this->assertSame([$january->id], $ids('date_to=2025-01-31'));

        $this->withToken($this->token)->getJson('/api/v1/parked-documents?per_page=1')
            ->assertJsonPath('data.0.id', $march->id)
            ->assertJsonPath('data.0.parked_by.id', $this->user->id)
            ->assertJsonPath('meta.total', 3);
    }

    // -------------------------------------------------------------------------
    // Post
    // -------------------------------------------------------------------------

    public function test_post_books_a_posted_journal_entry_and_marks_the_document_posted(): void
    {
        $doc = $this->makeDocument([
            'reference'     => 'PARK-1',
            'document_data' => ['description' => 'Parked sale', 'lines' => $this->postableLines()],
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parked-documents/' . $doc->uuid . '/post');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Parked document posted successfully.')
            ->assertJsonPath('data.parked_document.status', ParkedDocument::STATUS_POSTED)
            ->assertJsonPath('data.parked_document.approved_by', $this->user->id);

        $this->assertDatabaseHas('journal_entries', [
            'id'          => $response->json('data.journal_entry_id'),
            'status'      => 'posted',
            'reference'   => 'PARK-1',
            'description' => 'Parked sale',
            'source_type' => ParkedDocument::class,
            'source_id'   => $doc->id,
        ]);
    }

    public function test_post_refuses_a_document_posted_since_it_was_loaded(): void
    {
        $this->actingAs($this->user, 'api');
        $doc = $this->makeDocument(['document_data' => ['lines' => $this->postableLines()]]);
        $stale = ParkedDocument::findOrFail($doc->id);
        $service = app(ParkedDocumentService::class);

        $service->post(ParkedDocument::findOrFail($doc->id), $this->user->id);

        try {
            $service->post($stale, $this->user->id);
            $this->fail('A document posted since it was loaded was posted again.');
        } catch (BusinessRuleException $e) {
            $this->assertSame('INVALID_STATUS', $e->getErrorCode());
        }

        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_post_without_lines_is_refused_and_leaves_the_document_parked(): void
    {
        $doc = $this->makeDocument();

        $this->withToken($this->token)
            ->postJson('/api/v1/parked-documents/' . $doc->uuid . '/post')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'POST_FAILED')
            ->assertJsonPath('error.message', 'Parked document has no journal lines in document_data.lines.');

        $this->assertSame(ParkedDocument::STATUS_PARKED, $doc->fresh()->status);
    }

    public function test_post_refuses_a_posted_document(): void
    {
        $doc = $this->makeDocument(['status' => ParkedDocument::STATUS_POSTED]);

        $this->withToken($this->token)
            ->postJson('/api/v1/parked-documents/' . $doc->uuid . '/post')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_store_parks_with_the_parker_and_organization(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parked-documents', [
                'document_type' => 'journal_entry',
                'document_date' => '2025-01-15',
                'posting_date'  => '2025-01-16',
                'document_data' => ['lines' => []],
                'total_debit'   => 10,
                'total_credit'  => 10,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Document parked successfully.')
            ->assertJsonPath('data.status', ParkedDocument::STATUS_PARKED)
            ->assertJsonPath('data.parked_by', $this->user->id)
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_destroy_marks_the_document_rejected_before_deleting_it(): void
    {
        $doc = $this->makeDocument();

        $this->withToken($this->token)->deleteJson('/api/v1/parked-documents/' . $doc->uuid)
            ->assertStatus(200)
            ->assertJsonPath('message', 'Parked document deleted.');

        $this->assertSame(ParkedDocument::STATUS_REJECTED, ParkedDocument::withTrashed()->find($doc->id)->status);
    }

    public function test_index_returns_paginated_list(): void
    {
        $this->makeDocument();
        $this->makeDocument();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parked-documents');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_initially(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parked-documents');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_store_parks_a_document(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parked-documents', [
                'document_type' => 'vendor_invoice',
                'document_date' => '2025-01-15',
                'posting_date'  => '2025-01-15',
                'document_data' => ['description' => 'Test'],
                'total_debit'   => 500.00,
                'total_credit'  => 500.00,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parked-documents', []);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_show_returns_document_details(): void
    {
        $doc = $this->makeDocument();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parked-documents/' . $doc->uuid);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $doc->id);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/parked-documents/' . fake()->uuid());

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_update_modifies_parked_document(): void
    {
        $doc = $this->makeDocument(['status' => ParkedDocument::STATUS_PARKED]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/parked-documents/' . $doc->uuid, [
                'reference' => 'REF-001',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('REF-001', $doc->fresh()->reference);
    }

    public function test_update_rejects_posted_document(): void
    {
        $doc = $this->makeDocument(['status' => ParkedDocument::STATUS_POSTED]);

        $response = $this->withToken($this->token)
            ->putJson('/api/v1/parked-documents/' . $doc->uuid, [
                'reference' => 'REF-001',
            ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Approve
    // -------------------------------------------------------------------------

    public function test_approve_marks_parked_document(): void
    {
        $doc = $this->makeDocument(['status' => ParkedDocument::STATUS_PARKED]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/parked-documents/' . $doc->uuid . '/approve');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_destroy_rejects_posted_document(): void
    {
        $doc = $this->makeDocument(['status' => ParkedDocument::STATUS_POSTED]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/parked-documents/' . $doc->uuid);

        $response->assertStatus(422);
    }

    public function test_destroy_soft_deletes_parked_document(): void
    {
        $doc = $this->makeDocument(['status' => ParkedDocument::STATUS_PARKED]);

        $response = $this->withToken($this->token)
            ->deleteJson('/api/v1/parked-documents/' . $doc->uuid);

        $response->assertStatus(200);
        $this->assertSoftDeleted('parked_documents', ['id' => $doc->id]);
    }

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/parked-documents')->assertStatus(401);
    }
}
