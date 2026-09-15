<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ChecksOwnedReferences;
use Tests\Traits\TestHelpers;

/**
 * Receivables, credit and dispute requests accept only the caller's
 * organization's contacts and users.
 */
class ReceivablesOwnedReferencesTest extends TestCase
{
    use ChecksOwnedReferences;
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.ar-interest-runs.view',
            'accounting.ar-interest-runs.manage',
            'accounting.credit.manage',
            'accounting.disputes.manage',
        ]);
    }

    public function test_ar_interest_run_contact(): void
    {
        $this->assertOnlyOwnRowsAccepted('GET', '/api/v1/ar-interest-runs/preview', ['contact_id' => 'contacts']);
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/ar-interest-runs/execute', ['contact_id' => 'contacts']);
    }

    public function test_credit_limit_contact(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/credit-management/limits', ['contact_id' => 'contacts']);

        $contact = $this->ownRow('contacts');

        $this->withToken($this->token)
            ->postJson('/api/v1/credit-management/limits', [
                'contact_id' => $contact,
                'credit_limit' => 1000,
                'valid_from' => '2025-01-01',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('credit_limits', [
            'organization_id' => $this->organization->id,
            'contact_id' => $contact,
        ]);
    }

    public function test_dispute_assignee(): void
    {
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/disputes', ['assigned_to' => 'users']);
        $this->assertOnlyOwnRowsAccepted('POST', '/api/v1/disputes/promise-to-pay', ['assigned_to' => 'users']);

        $dispute = $this->ownRow('dispute_cases');
        $this->assertOnlyOwnRowsAccepted('PUT', "/api/v1/disputes/{$dispute}", ['assigned_to' => 'users']);
    }
}
