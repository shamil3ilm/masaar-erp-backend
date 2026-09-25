<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Manufacturing\Complaint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsTenantRows;
use Tests\Traits\TestHelpers;

class ComplaintTest extends TestCase
{
    use BuildsTenantRows, RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.manage',
            'manufacturing.quality.view',
        ]);
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_complaints(): void
    {
        Complaint::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson('/api/v1/manufacturing/complaints', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_complaint(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/complaints',
            [
                'complaint_number'  => 'CMP-2026-001',
                'complaint_source'  => 'customer',
                'subject'           => 'Product quality issue',
                'description'       => 'Customer reported defects in delivered batch',
                'priority'          => 'high',
                'received_date'     => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('complaints', [
            'complaint_number' => 'CMP-2026-001',
            'organization_id'  => $this->organization->id,
        ]);
    }

    public function test_store_requires_complaint_number(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/complaints',
            [
                'complaint_source' => 'customer',
                'subject'          => 'Issue',
                'description'      => 'Detail',
                'priority'         => 'high',
                'received_date'    => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    public function test_store_requires_description(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/complaints',
            [
                'complaint_number' => 'CMP-2026-002',
                'complaint_source' => 'customer',
                'subject'          => 'Issue',
                'priority'         => 'high',
                'received_date'    => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertUnprocessable();
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_complaint(): void
    {
        $complaint = Complaint::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->getJson(
            "/api/v1/manufacturing/complaints/{$complaint->id}",
            $this->authHeaders()
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── addCommunication ─────────────────────────────────────────────────────

    public function test_add_communication_creates_log(): void
    {
        $complaint = Complaint::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->postJson(
            "/api/v1/manufacturing/complaints/{$complaint->id}/communications",
            [
                'direction' => 'inbound',
                'channel'   => 'email',
                'content'   => 'Customer replied with additional details',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('complaint_communications', [
            'complaint_id' => $complaint->id,
        ]);
    }

    // ─── number scope ─────────────────────────────────────────────────────────

    public function test_store_accepts_a_complaint_number_another_organization_uses(): void
    {
        $this->tenantRow('complaints', $this->otherTenant()->id, ['complaint_number' => 'CMP-2026-900']);

        $response = $this->postJson(
            '/api/v1/manufacturing/complaints',
            [
                'complaint_number' => 'CMP-2026-900',
                'complaint_source' => 'customer',
                'subject'          => 'Product quality issue',
                'description'      => 'Customer reported defects in delivered batch',
                'priority'         => 'high',
                'received_date'    => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertCreated();
    }

    public function test_store_refuses_a_complaint_number_the_organization_already_uses(): void
    {
        $this->tenantRow('complaints', $this->organization->id, ['complaint_number' => 'CMP-2026-901']);

        $response = $this->postJson(
            '/api/v1/manufacturing/complaints',
            [
                'complaint_number' => 'CMP-2026-901',
                'complaint_source' => 'customer',
                'subject'          => 'Product quality issue',
                'description'      => 'Customer reported defects in delivered batch',
                'priority'         => 'high',
                'received_date'    => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['complaint_number']);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/complaints')->assertUnauthorized();
    }
}
