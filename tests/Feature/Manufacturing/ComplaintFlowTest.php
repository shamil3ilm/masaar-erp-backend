<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\Complaint;
use App\Models\Manufacturing\ComplaintResolution;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\BuildsInventory;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Complaints show their customer by reference, accept only the organization's
 * contacts and users, are reached only within the organization, and record a
 * resolution together with the resolved status.
 */
class ComplaintFlowTest extends TestCase
{
    use BuildsInventory, BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.manage',
        ]);

        $this->customer = Contact::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_complaints_show_the_customer_by_reference(): void
    {
        $complaint = $this->complaint();

        $listed = $this->apiGet('/manufacturing/complaints')->assertOk()->json('data.0.contact');
        $shown = $this->apiGet("/manufacturing/complaints/{$complaint->id}")->assertOk()->json('data.contact');

        $this->assertEqualsCanonicalizing(Contact::REFERENCE_COLUMNS, array_keys($listed));
        $this->assertEqualsCanonicalizing(Contact::REFERENCE_COLUMNS, array_keys($shown));
    }

    public function test_resolving_records_the_resolution_and_the_status(): void
    {
        $complaint = $this->complaint();

        $this->apiPost("/manufacturing/complaints/{$complaint->id}/resolve", [
            'resolution_type' => 'replacement',
            'resolution_description' => 'Unit replaced',
            'customer_accepted' => true,
        ])->assertCreated()->assertJsonPath('data.resolved_by_id', $this->user->id);

        $this->assertSame('resolved', $complaint->fresh()->status);
        $this->assertSame(1, ComplaintResolution::where('complaint_id', $complaint->id)->count());
    }

    public function test_a_resolution_is_not_kept_when_the_status_change_fails(): void
    {
        $complaint = $this->complaint();

        Complaint::updating(function (): void {
            throw new RuntimeException('Changing the status failed.');
        });

        $this->apiPost("/manufacturing/complaints/{$complaint->id}/resolve", [
            'resolution_type' => 'refund',
            'resolution_description' => 'Refunded',
        ])->assertStatus(500);

        $this->assertSame(0, ComplaintResolution::where('complaint_id', $complaint->id)->count());
    }

    public function test_another_organizations_contact_and_user_are_refused(): void
    {
        $theirCustomer = Contact::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $this->apiPost('/manufacturing/complaints', [
            'complaint_number' => 'CMP-X-1',
            'complaint_source' => 'customer',
            'contact_id' => $theirCustomer->id,
            'subject' => 'Broken',
            'description' => 'Arrived broken',
            'priority' => 'high',
            'assigned_to_id' => $this->foreignUser()->id,
            'received_date' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['contact_id', 'assigned_to_id']);
    }

    public function test_another_organizations_complaint_is_not_found(): void
    {
        $theirs = Complaint::factory()->create(['organization_id' => $this->otherOrganization()->id]);

        $this->apiGet("/manufacturing/complaints/{$theirs->id}")->assertNotFound();
        $this->apiPost("/manufacturing/complaints/{$theirs->id}/communications", [
            'direction' => 'inbound',
            'channel' => 'email',
            'content' => 'Hello',
        ])->assertNotFound();
        $this->apiPost("/manufacturing/complaints/{$theirs->id}/resolve", [
            'resolution_type' => 'refund',
            'resolution_description' => 'Refunded',
        ])->assertNotFound();

        $this->assertSame('open', Complaint::withoutGlobalScopes()->find($theirs->id)->status);
    }

    private function complaint(): Complaint
    {
        return Complaint::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_id' => $this->customer->id,
        ]);
    }
}
