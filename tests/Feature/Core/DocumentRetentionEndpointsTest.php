<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\DocumentLegalHold;
use App\Models\Core\Organization;
use App\Models\Core\RetentionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the document retention endpoints: retention policies and legal holds
 * of the caller's organization. A policy named in the URL is the one shown,
 * changed or deleted; another organization's policy or hold is not found.
 */
class DocumentRetentionEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.settings.view', 'core.settings.edit']);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_policies_are_listed_by_document_type_for_the_organization(): void
    {
        $invoice = $this->policy($this->organization->id, 'invoice');
        $bill = $this->policy($this->organization->id, 'bill');
        $this->policy($this->otherOrg->id, 'payslip');

        $this->assertSame(
            [$bill->id, $invoice->id],
            array_column($this->apiGet('/retention/policies')->assertOk()->json('data'), 'id')
        );
    }

    public function test_the_policy_in_the_url_is_shown_updated_and_deleted(): void
    {
        $policy = $this->policy($this->organization->id, 'invoice');

        $this->apiGet("/retention/policies/{$policy->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Retention policy retrieved.')
            ->assertJsonPath('data.id', $policy->id);

        $this->apiPut("/retention/policies/{$policy->id}", ['retention_years' => 12])
            ->assertOk()
            ->assertJsonPath('message', 'Retention policy updated.')
            ->assertJsonPath('data.retention_years', 12);
        $this->assertSame(12, $policy->fresh()->retention_years);

        $this->apiDelete("/retention/policies/{$policy->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Retention policy deleted.');
        $this->assertNull(RetentionPolicy::find($policy->id));
    }

    public function test_another_organizations_policy_and_hold_are_not_found(): void
    {
        $policy = $this->policy($this->otherOrg->id, 'invoice');
        $hold = DocumentLegalHold::withoutGlobalScopes()->create([
            'organization_id' => $this->otherOrg->id,
            'document_type' => 'invoice',
            'document_id' => 1,
            'hold_reason' => 'Audit',
            'held_by' => $this->user->id,
            'is_active' => true,
        ]);

        $this->apiGet("/retention/policies/{$policy->id}")->assertNotFound();
        $this->apiPut("/retention/policies/{$policy->id}", ['retention_years' => 1])->assertNotFound();
        $this->apiDelete("/retention/policies/{$policy->id}")->assertNotFound();
        $this->apiDelete("/retention/legal-holds/{$hold->id}")->assertNotFound();

        $this->assertNotNull(RetentionPolicy::withoutGlobalScopes()->find($policy->id));
        $this->assertTrue((bool) DocumentLegalHold::withoutGlobalScopes()->findOrFail($hold->id)->is_active);
    }

    public function test_a_legal_hold_is_placed_listed_and_released(): void
    {
        $id = $this->apiPost('/retention/legal-holds', [
            'document_type' => 'invoice',
            'document_id' => 42,
            'hold_reason' => 'Litigation',
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Legal hold placed.')
            ->json('data.id');

        $this->apiGet('/retention/legal-holds')
            ->assertOk()
            ->assertJsonPath('message', 'Legal holds retrieved.')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $id);

        $this->apiDelete("/retention/legal-holds/{$id}")->assertOk()->assertJsonPath('message', 'Legal hold released.');
        $this->assertFalse((bool) DocumentLegalHold::findOrFail($id)->is_active);
    }

    private function policy(int $organizationId, string $documentType): RetentionPolicy
    {
        return RetentionPolicy::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'document_type' => $documentType,
            'policy_name' => ucfirst($documentType).' policy',
            'retention_years' => 10,
            'jurisdiction' => 'saudi_arabia',
            'action_on_expiry' => 'archive',
            'legal_hold_override' => true,
            'is_active' => true,
        ]);
    }
}
