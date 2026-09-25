<?php

declare(strict_types=1);

namespace Tests\Feature\Aml;

use App\Jobs\RunAmlScreeningJob;
use App\Models\Aml\AmlRiskScore;
use App\Models\Aml\AmlSuspiciousActivity;
use App\Models\Aml\AmlTransactionFlag;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Anti-money-laundering review: risk scores, flagged transactions, suspicious
 * activity reports and re-screening a contact.
 *
 * These listings are triage views, so a contact appears in them by its
 * reference columns only and a report's creator by id and name.
 */
class AmlEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrganization;

    private Contact $contact;

    private Contact $foreignContact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['aml.sar.view', 'aml.sar.manage', 'aml.screening.manage']);
        $this->otherOrganization = Organization::factory()->create();

        $this->contact = Contact::factory()->create(['organization_id' => $this->organization->id, 'company_name' => 'Gulf Traders']);
        $this->foreignContact = Contact::factory()->create(['organization_id' => $this->otherOrganization->id]);
    }

    public function test_risk_scores_list_this_organizations_scores_with_contacts_by_reference(): void
    {
        $this->riskScore($this->contact, ['risk_level' => AmlRiskScore::CRITICAL]);
        $this->riskScore($this->foreignContact, [], $this->otherOrganization->id);

        $response = $this->apiGet('/aml/risk-scores?risk_level=critical')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.contact.company_name', 'Gulf Traders');

        $this->assertSame(Contact::REFERENCE_COLUMNS, array_keys($response->json('data.0.contact')));
    }

    public function test_a_contacts_risk_is_shown_and_another_organizations_is_not_found(): void
    {
        $this->riskScore($this->contact);
        $this->riskScore($this->foreignContact, [], $this->otherOrganization->id);

        $this->apiGet("/aml/risk-scores/{$this->contact->id}")
            ->assertOk()
            ->assertJsonPath('data.contact.id', $this->contact->id)
            ->assertJsonPath('data.score', 85);

        $this->apiGet("/aml/risk-scores/{$this->foreignContact->id}")->assertNotFound();
    }

    public function test_transaction_flags_are_filtered_and_show_contacts_by_reference(): void
    {
        $this->flag(['flag_reason' => AmlTransactionFlag::LARGE_CASH]);
        $this->flag(['flag_reason' => AmlTransactionFlag::STRUCTURING]);

        $response = $this->apiGet('/aml/transactions/flagged?flag_reason=large_cash&contact_id='.$this->contact->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame(Contact::REFERENCE_COLUMNS, array_keys($response->json('data.0.contact')));
    }

    public function test_a_report_is_created_with_its_type_and_listed(): void
    {
        $report = $this->apiPost('/aml/sar', [
            'contact_id' => $this->contact->id,
            'activity_type' => 'structuring',
            'transaction_ids' => [11, 12],
            'description' => 'Repeated payments just under the reporting threshold.',
            'report_type' => 'STR',
        ])->assertCreated()
            ->assertJsonPath('message', 'SAR created successfully.')
            ->assertJsonPath('data.report_type', 'STR')
            ->assertJsonPath('data.contact_name', 'Gulf Traders')
            ->json('data');

        $this->assertSame(['id', 'name'], array_keys($report['creator']));
        $this->assertSame(Contact::REFERENCE_COLUMNS, array_keys($report['contact']));

        $this->apiGet('/aml/sar?report_type=STR')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_report_refuses_a_contact_of_another_organization(): void
    {
        $this->apiPost('/aml/sar', [
            'contact_id' => $this->foreignContact->id,
            'activity_type' => 'structuring',
            'transaction_ids' => [1],
            'description' => 'Cross-tenant probe.',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, AmlSuspiciousActivity::withoutGlobalScopes()->count());
    }

    public function test_screening_is_queued_for_this_organizations_contact_only(): void
    {
        Queue::fake();

        $this->apiPost("/aml/screen-contact/{$this->contact->id}")
            ->assertOk()
            ->assertJsonPath('message', 'AML screening dispatched for contact.');

        $this->apiPost("/aml/screen-contact/{$this->foreignContact->id}")->assertNotFound();

        Queue::assertPushed(RunAmlScreeningJob::class, 1);
    }

    public function test_a_failed_dispatch_does_not_reveal_its_cause(): void
    {
        $dispatcher = app(Dispatcher::class);

        $this->mock(Dispatcher::class, function ($mock) use ($dispatcher): void {
            $mock->shouldReceive('dispatch')->andReturnUsing(function ($job) use ($dispatcher) {
                if ($job instanceof RunAmlScreeningJob) {
                    throw new \RuntimeException('redis://queue-user:s3cret@queue.internal:6379');
                }

                return $dispatcher->dispatch($job);
            });
            $mock->shouldIgnoreMissing();
        });

        $response = $this->apiPost("/aml/screen-contact/{$this->contact->id}")
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'DISPATCH_FAILED');

        $this->assertStringNotContainsString('s3cret', $response->getContent());
    }

    private function riskScore(Contact $contact, array $attributes = [], ?int $organizationId = null): AmlRiskScore
    {
        return AmlRiskScore::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $organizationId ?? $this->organization->id,
            'contact_id' => $contact->id,
            'score' => 85,
            'risk_level' => AmlRiskScore::HIGH,
            'score_breakdown' => ['sanctions' => 50, 'volume' => 35],
            'sanctions_hit' => false,
            'pep_hit' => false,
        ], $attributes));
    }

    private function flag(array $attributes = []): AmlTransactionFlag
    {
        return AmlTransactionFlag::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $this->organization->id,
            'transaction_type' => 'invoice',
            'transaction_id' => 1,
            'amount' => 60000,
            'currency' => 'SAR',
            'flag_reason' => AmlTransactionFlag::LARGE_CASH,
            'status' => AmlTransactionFlag::STATUS_FLAGGED,
            'context' => ['threshold' => 55000],
            'contact_id' => $this->contact->id,
            'transaction_date' => now(),
        ], $attributes));
    }
}
