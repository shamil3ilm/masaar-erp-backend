<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\BuildsTenantRows;
use Tests\Traits\TestHelpers;

class SupplierQualityTest extends TestCase
{
    use BuildsTenantRows, RefreshDatabase, TestHelpers;

    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.manage',
            'manufacturing.quality.view',
        ]);

        $this->supplier = Contact::factory()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    // ─── ratings ──────────────────────────────────────────────────────────────

    public function test_ratings_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/supplier-quality/ratings', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_rating_creates_it(): void
    {
        $response = $this->postJson(
            '/api/v1/manufacturing/supplier-quality/ratings',
            [
                'supplier_id'         => $this->supplier->id,
                'rating_period_start' => now()->subMonth()->format('Y-m-d'),
                'rating_period_end'   => now()->format('Y-m-d'),
                'classification'      => 'approved',
            ],
            $this->authHeaders()
        );

        $response->assertCreated()->assertJsonPath('success', true);
    }

    // ─── avl ──────────────────────────────────────────────────────────────────

    public function test_avl_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/supplier-quality/avl', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    // ─── ncrs ─────────────────────────────────────────────────────────────────

    public function test_ncrs_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/manufacturing/supplier-quality/ncrs', $this->authHeaders());

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_store_ncr_accepts_a_number_another_organization_uses(): void
    {
        $this->tenantRow('supplier_ncr_records', $this->otherTenant()->id, ['ncr_number' => 'NCR-2026-900']);

        $response = $this->postJson(
            '/api/v1/manufacturing/supplier-quality/ncrs',
            [
                'ncr_number'                 => 'NCR-2026-900',
                'supplier_id'                => $this->supplier->id,
                'nonconformance_description' => 'Wrong alloy delivered',
                'severity'                   => 'major',
                'detected_date'              => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertCreated();
    }

    public function test_store_ncr_refuses_a_number_the_organization_already_uses(): void
    {
        $this->tenantRow('supplier_ncr_records', $this->organization->id, ['ncr_number' => 'NCR-2026-901']);

        $response = $this->postJson(
            '/api/v1/manufacturing/supplier-quality/ncrs',
            [
                'ncr_number'                 => 'NCR-2026-901',
                'supplier_id'                => $this->supplier->id,
                'nonconformance_description' => 'Wrong alloy delivered',
                'severity'                   => 'major',
                'detected_date'              => now()->format('Y-m-d'),
            ],
            $this->authHeaders()
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['ncr_number']);
    }

    // ─── auth guard ───────────────────────────────────────────────────────────

    public function test_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/manufacturing/supplier-quality/ratings')->assertUnauthorized();
    }
}
