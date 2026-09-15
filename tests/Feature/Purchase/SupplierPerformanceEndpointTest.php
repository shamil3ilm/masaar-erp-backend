<?php

declare(strict_types=1);

namespace Tests\Feature\Purchase;

use App\Models\Core\Organization;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\SupplierDeliveryRecord;
use App\Models\Purchase\SupplierEvaluationCriteria;
use App\Models\Purchase\SupplierIncident;
use App\Models\Purchase\SupplierScorecard;
use App\Models\Sales\Contact;
use App\Services\Purchase\SupplierPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Supplier performance endpoints: ids a request names must be the caller's
 * organization's, suppliers leave with their tax number masked, and a
 * scorecard is finalized and an incident resolved on the locked row.
 */
class SupplierPerformanceEndpointTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const TAX_NUMBER = '300123456700003';

    private string $baseUrl = '/purchase/supplier-performance';
    private Contact $supplier;
    private SupplierEvaluationCriteria $criterion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'purchase.suppliers.view', 'purchase.suppliers.create',
            'purchase.suppliers.edit', 'purchase.suppliers.delete',
        ]);

        $this->supplier = Contact::factory()->supplier()->create([
            'organization_id' => $this->organization->id,
            'tax_number' => self::TAX_NUMBER,
        ]);
        $this->criterion = $this->criterion($this->organization);
    }

    public function test_scorecards_list_only_this_organizations_with_masked_suppliers(): void
    {
        $this->scorecard($this->organization, $this->supplier);
        $other = Organization::factory()->create();
        $this->scorecard($other, Contact::factory()->supplier()->create(['organization_id' => $other->id]));

        $response = $this->apiGet("{$this->baseUrl}/scorecards")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.supplier.tax_number', '***********0003');

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_storing_a_scorecard_refuses_another_organizations_supplier_and_criterion(): void
    {
        $other = Organization::factory()->create();

        $this->apiPost("{$this->baseUrl}/scorecards", [
            'supplier_id' => Contact::factory()->supplier()->create(['organization_id' => $other->id])->id,
            'evaluation_period_start' => '2026-01-01',
            'evaluation_period_end' => '2026-03-31',
            'ratings' => [['criterion_id' => $this->criterion($other)->id, 'score' => 80]],
        ])->assertStatus(422)->assertJsonValidationErrors(['supplier_id', 'ratings.0.criterion_id']);

        $this->assertSame(0, SupplierScorecard::withoutGlobalScopes()->count());
    }

    public function test_a_scorecard_is_created_and_finalized_once(): void
    {
        $scorecardId = $this->apiPost("{$this->baseUrl}/scorecards", [
            'supplier_id' => $this->supplier->id,
            'evaluation_period_start' => '2026-01-01',
            'evaluation_period_end' => '2026-03-31',
            'ratings' => [['criterion_id' => $this->criterion->id, 'score' => 80]],
        ])->assertCreated()
            ->assertJsonPath('data.supplier.tax_number', '***********0003')
            ->json('data.id');

        $this->apiPost("{$this->baseUrl}/scorecards/{$scorecardId}/finalize")
            ->assertOk()
            ->assertJsonPath('data.status', SupplierScorecard::STATUS_FINALIZED);

        $this->apiPost("{$this->baseUrl}/scorecards/{$scorecardId}/finalize")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SCORECARD_ALREADY_FINALIZED');
    }

    public function test_finalizing_a_stale_copy_of_a_finalized_scorecard_is_refused(): void
    {
        $scorecard = $this->scorecard($this->organization, $this->supplier);
        $staleCopy = SupplierScorecard::withoutGlobalScopes()->findOrFail($scorecard->id);
        $service = app(SupplierPerformanceService::class);

        $finalized = $service->finalizeScorecard($scorecard, $this->user->id);

        try {
            $service->finalizeScorecard($staleCopy, $this->user->id);
            $this->fail('A finalized scorecard was finalized again from a stale copy.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Scorecard is already finalized.', $e->getMessage());
        }

        $this->assertEquals($finalized->finalized_at, $scorecard->fresh()->finalized_at);
    }

    public function test_updating_another_organizations_criteria_is_not_found(): void
    {
        $foreignCriterion = $this->criterion(Organization::factory()->create());

        $this->apiPut("{$this->baseUrl}/criteria/{$foreignCriterion->id}", ['name' => 'Changed'])
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Criteria not found.');
    }

    public function test_recording_a_delivery_refuses_another_organizations_order_and_supplier(): void
    {
        $other = Organization::factory()->create();
        $foreignSupplier = Contact::factory()->supplier()->create(['organization_id' => $other->id]);

        $this->apiPost("{$this->baseUrl}/delivery-records", [
            'purchase_order_id' => PurchaseOrder::factory()->create([
                'organization_id' => $other->id,
                'supplier_id' => $foreignSupplier->id,
            ])->id,
            'supplier_id' => $foreignSupplier->id,
            'promised_date' => '2026-03-01',
            'quantity_ordered' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors(['purchase_order_id', 'supplier_id']);

        $this->assertSame(0, SupplierDeliveryRecord::withoutGlobalScopes()->count());
    }

    public function test_incidents_list_with_masked_suppliers(): void
    {
        $this->incident($this->supplier);

        $response = $this->apiGet("{$this->baseUrl}/incidents")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.supplier.tax_number', '***********0003');

        $this->assertStringNotContainsString(self::TAX_NUMBER, $response->getContent());
    }

    public function test_resolving_a_stale_copy_of_a_resolved_incident_is_refused(): void
    {
        $incident = $this->incident($this->supplier);
        $staleCopy = SupplierIncident::withoutGlobalScopes()->findOrFail($incident->id);

        $this->apiPost("{$this->baseUrl}/incidents/{$incident->id}/resolve", ['resolution_notes' => 'Replaced'])
            ->assertOk();

        try {
            app(SupplierPerformanceService::class)->resolveIncident($staleCopy, 'Overwritten', $this->user->id);
            $this->fail('A resolved incident was resolved again from a stale copy.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Incident is already resolved.', $e->getMessage());
        }

        $this->assertSame('Replaced', $incident->fresh()->resolution_notes);
    }

    private function criterion(Organization $organization): SupplierEvaluationCriteria
    {
        return SupplierEvaluationCriteria::create([
            'organization_id' => $organization->id,
            'name' => 'On-time delivery',
            'category' => 'delivery',
            'weight_percent' => 100,
            'is_active' => true,
        ]);
    }

    private function scorecard(Organization $organization, Contact $supplier): SupplierScorecard
    {
        return SupplierScorecard::create([
            'organization_id' => $organization->id,
            'supplier_id' => $supplier->id,
            'evaluation_period_start' => '2026-01-01',
            'evaluation_period_end' => '2026-03-31',
            'status' => SupplierScorecard::STATUS_DRAFT,
            'created_by' => $this->user->id,
        ]);
    }

    private function incident(Contact $supplier): SupplierIncident
    {
        return SupplierIncident::create([
            'organization_id' => $supplier->organization_id,
            'supplier_id' => $supplier->id,
            'incident_type' => SupplierIncident::TYPE_LATE_DELIVERY,
            'severity' => SupplierIncident::SEVERITY_HIGH,
            'description' => 'Two weeks late',
            'occurred_at' => '2026-03-01',
            'created_by' => $this->user->id,
        ]);
    }
}
