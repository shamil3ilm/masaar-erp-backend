<?php

declare(strict_types=1);

namespace Tests\Feature\Aml;

use App\Jobs\RunAmlEscalationJob;
use App\Models\Aml\AmlRiskScore;
use App\Models\Aml\AmlSuspiciousActivity;
use App\Models\Aml\AmlTransactionFlag;
use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentReceived;
use App\Models\User;
use App\Services\Aml\AmlMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The detection logic behind an AML flag, a contact's risk score and a
 * suspicious activity report: what trips each control, what does not, and the
 * record each one leaves behind.
 *
 * One organization is never assessed against another's transactions, so every
 * scoped check is proved against a second organization's rows as well.
 */
class AmlMonitoringServiceTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrganization;

    private Contact $contact;

    private AmlMonitoringService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->otherOrganization = Organization::factory()->create();
        $this->contact = $this->contact($this->organization->id);

        $this->service = app(AmlMonitoringService::class);
    }

    // ----------------------------------------------------------------
    // Threshold breach
    // ----------------------------------------------------------------

    public function test_a_transaction_at_the_threshold_is_flagged_and_one_cent_under_is_not(): void
    {
        // The 10,000 line is written into the service and the comparison is
        // >=, so the threshold itself is a breach.
        $this->screen(1, 10000.0);
        $this->assertSame([AmlTransactionFlag::THRESHOLD_BREACH], $this->flagReasons());

        $this->screen(2, 9999.99);
        $this->assertSame(1, AmlTransactionFlag::withoutGlobalScopes()->count());
    }

    public function test_a_threshold_breach_records_the_transaction_its_score_and_the_line_it_crossed(): void
    {
        $this->screen(77, 25000.0, 'invoice', 'SAR', $this->contact->id);

        $flag = AmlTransactionFlag::withoutGlobalScopes()->sole();
        $this->assertSame($this->organization->id, $flag->organization_id);
        $this->assertSame('invoice', $flag->transaction_type);
        $this->assertSame(77, $flag->transaction_id);
        $this->assertSame($this->contact->id, $flag->contact_id);
        $this->assertSame(AmlTransactionFlag::THRESHOLD_BREACH, $flag->flag_reason);
        $this->assertSame(AmlTransactionFlag::STATUS_FLAGGED, $flag->status);
        $this->assertSame(30, $flag->aml_score);
        $this->assertSame('SAR', $flag->currency);
        $this->assertEquals(25000.0, $flag->amount);
        $this->assertEquals(10000.0, $flag->context['threshold']);
    }

    public function test_screening_the_same_transaction_twice_writes_the_flag_twice(): void
    {
        // Nothing de-duplicates: a re-screening of the same transaction leaves
        // a second, identical flag. Listed as a finding.
        $this->screen(5, 25000.0);
        $this->screen(5, 25000.0);

        $this->assertSame(2, AmlTransactionFlag::withoutGlobalScopes()->where('transaction_id', 5)->count());
    }

    // ----------------------------------------------------------------
    // Structuring
    // ----------------------------------------------------------------

    public function test_structuring_is_flagged_on_the_third_payment_inside_the_band(): void
    {
        // The band is 8000.00 – 9999.99, and three payments inside it in seven
        // days are the pattern.
        $this->payment($this->organization->id, $this->contact->id, 8500);
        $this->payment($this->organization->id, $this->contact->id, 8500);
        $this->screen(1, 100.0, 'payment', 'SAR', $this->contact->id);
        $this->assertSame([], $this->flagReasons());

        $this->payment($this->organization->id, $this->contact->id, 8500);
        $this->screen(2, 100.0, 'payment', 'SAR', $this->contact->id);
        $this->assertSame([AmlTransactionFlag::STRUCTURING], $this->flagReasons());
    }

    public function test_structuring_ignores_payments_at_the_edges_of_the_band(): void
    {
        $this->payment($this->organization->id, $this->contact->id, 7999.99); // under the band
        $this->payment($this->organization->id, $this->contact->id, 10000);   // over the band
        $this->payment($this->organization->id, $this->contact->id, 8000);    // floor — inside
        $this->payment($this->organization->id, $this->contact->id, 9999.99); // ceiling — inside
        $this->screen(1, 100.0, 'payment', 'SAR', $this->contact->id);
        $this->assertSame([], $this->flagReasons());

        $this->payment($this->organization->id, $this->contact->id, 9000);
        $this->screen(2, 100.0, 'payment', 'SAR', $this->contact->id);
        $this->assertSame([AmlTransactionFlag::STRUCTURING], $this->flagReasons());
    }

    public function test_structuring_ignores_another_organizations_payments(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->payment($this->otherOrganization->id, $this->contact->id, 8500);
        }

        $this->screen(1, 100.0, 'payment', 'SAR', $this->contact->id);

        $this->assertSame([], $this->flagReasons());
    }

    public function test_structuring_ignores_payments_older_than_the_window_and_of_other_contacts(): void
    {
        $other = $this->contact($this->organization->id);

        $stale = [
            $this->payment($this->organization->id, $this->contact->id, 8500),
            $this->payment($this->organization->id, $this->contact->id, 8500),
        ];
        DB::table('payments_received')->whereIn('id', $stale)->update(['created_at' => now()->subDays(8)]);
        $this->payment($this->organization->id, $other->id, 8500);
        $this->payment($this->organization->id, $this->contact->id, 8500);

        $this->screen(1, 100.0, 'payment', 'SAR', $this->contact->id);

        $this->assertSame([], $this->flagReasons());
    }

    public function test_structuring_looks_at_payments_even_when_an_invoice_is_being_screened(): void
    {
        // The transaction type reaches the structuring check and is then
        // ignored: an invoice is judged on the contact's payment history.
        // Listed as a finding.
        for ($i = 0; $i < 3; $i++) {
            $this->payment($this->organization->id, $this->contact->id, 8500);
        }

        $this->screen(1, 100.0, 'invoice', 'SAR', $this->contact->id);

        $this->assertSame([AmlTransactionFlag::STRUCTURING], $this->flagReasons());
    }

    public function test_structuring_is_not_checked_without_a_contact(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->payment($this->organization->id, $this->contact->id, 8500);
        }

        $this->screen(1, 100.0, 'payment', 'SAR', null);

        $this->assertSame([], $this->flagReasons());
    }

    // ----------------------------------------------------------------
    // Rapid movement
    // ----------------------------------------------------------------

    public function test_rapid_movement_is_flagged_for_a_payment_against_a_fresh_invoice(): void
    {
        $this->invoiceFor($this->organization->id, $this->contact->id);

        $this->screen(1, 100.0, 'payment', 'SAR', $this->contact->id);

        $this->assertSame([AmlTransactionFlag::RAPID_MOVEMENT], $this->flagReasons());
    }

    public function test_rapid_movement_ignores_an_older_invoice_another_contacts_and_another_organizations(): void
    {
        $other = $this->contact($this->organization->id);

        $stale = $this->invoiceFor($this->organization->id, $this->contact->id);
        DB::table('invoices')->where('id', $stale)->update(['created_at' => now()->subMinutes(61)]);
        $this->invoiceFor($this->organization->id, $other->id);
        $this->invoiceFor($this->otherOrganization->id, $this->contact->id);

        $this->screen(1, 100.0, 'payment', 'SAR', $this->contact->id);

        $this->assertSame([], $this->flagReasons());
    }

    public function test_rapid_movement_is_not_checked_when_an_invoice_is_screened(): void
    {
        $this->invoiceFor($this->organization->id, $this->contact->id);

        $this->screen(1, 100.0, 'invoice', 'SAR', $this->contact->id);

        $this->assertSame([], $this->flagReasons());
    }

    // ----------------------------------------------------------------
    // High-risk contact
    // ----------------------------------------------------------------

    public function test_a_critical_contact_is_flagged_and_a_high_one_is_not(): void
    {
        $this->riskScore($this->organization->id, $this->contact->id, AmlRiskScore::HIGH);
        $this->screen(1, 100.0, 'payment', 'SAR', $this->contact->id);
        $this->assertSame([], $this->flagReasons());

        AmlRiskScore::withoutGlobalScopes()->delete();
        $this->riskScore($this->organization->id, $this->contact->id, AmlRiskScore::CRITICAL);
        $this->screen(2, 100.0, 'payment', 'SAR', $this->contact->id);
        $this->assertSame([AmlTransactionFlag::HIGH_RISK_CONTACT], $this->flagReasons());
    }

    public function test_another_organizations_rating_of_the_contact_does_not_flag_it_here(): void
    {
        $this->riskScore($this->otherOrganization->id, $this->contact->id, AmlRiskScore::CRITICAL);

        $this->screen(1, 100.0, 'payment', 'SAR', $this->contact->id);

        $this->assertSame([], $this->flagReasons());
    }

    // ----------------------------------------------------------------
    // Escalation
    // ----------------------------------------------------------------

    public function test_two_flags_on_one_transaction_queue_an_escalation_and_one_flag_does_not(): void
    {
        Queue::fake();

        $this->screen(1, 25000.0, 'payment', 'SAR', $this->contact->id);
        $this->assertSame([AmlTransactionFlag::THRESHOLD_BREACH], $this->flagReasons());
        Queue::assertNotPushed(RunAmlEscalationJob::class);

        $this->invoiceFor($this->organization->id, $this->contact->id);
        $this->screen(2, 25000.0, 'payment', 'SAR', $this->contact->id);
        Queue::assertPushed(RunAmlEscalationJob::class, 1);
    }

    public function test_three_flags_escalate_into_a_report_that_names_the_contact_and_the_reasons(): void
    {
        $this->riskScore($this->organization->id, $this->contact->id, AmlRiskScore::CRITICAL);
        $this->invoiceFor($this->organization->id, $this->contact->id);
        $this->screen(31, 25000.0, 'payment', 'SAR', $this->contact->id);

        $this->assertCount(3, $this->flagReasons());

        $this->escalate('payment', 31);

        $sar = AmlSuspiciousActivity::withoutGlobalScopes()->sole();
        $this->assertSame($this->organization->id, $sar->organization_id);
        $this->assertSame($this->contact->id, $sar->contact_id);
        $this->assertSame('Gulf Traders', $sar->contact_name);
        $this->assertSame(AmlSuspiciousActivity::SAR, $sar->report_type);
        $this->assertSame(AmlSuspiciousActivity::STATUS_DRAFT, $sar->status);
        $this->assertSame([31], $sar->related_transaction_ids);
        $this->assertNull($sar->created_by);
        $this->assertStringContainsString('3 AML flags', $sar->description);

        $this->assertSame(
            [AmlTransactionFlag::STATUS_ESCALATED],
            AmlTransactionFlag::withoutGlobalScopes()->pluck('status')->unique()->all(),
        );
    }

    public function test_a_second_escalation_of_the_same_transaction_does_not_file_a_second_report(): void
    {
        $this->riskScore($this->organization->id, $this->contact->id, AmlRiskScore::CRITICAL);
        $this->invoiceFor($this->organization->id, $this->contact->id);
        $this->screen(31, 25000.0, 'payment', 'SAR', $this->contact->id);

        $this->escalate('payment', 31);
        $this->escalate('payment', 31);

        $this->assertSame(1, AmlSuspiciousActivity::withoutGlobalScopes()->count());
    }

    public function test_two_flags_are_below_the_bar_the_escalation_job_files_a_report_at(): void
    {
        // screenTransaction queues the job at two flags while the job files a
        // report at three, so a two-flag transaction escalates to nothing.
        // Listed for a decision on which number is right.
        $this->invoiceFor($this->organization->id, $this->contact->id);
        $this->screen(31, 25000.0, 'payment', 'SAR', $this->contact->id);

        $this->assertCount(2, $this->flagReasons());

        $this->escalate('payment', 31);

        $this->assertSame(0, AmlSuspiciousActivity::withoutGlobalScopes()->count());
        $this->assertSame(
            [AmlTransactionFlag::STATUS_FLAGGED],
            AmlTransactionFlag::withoutGlobalScopes()->pluck('status')->unique()->all(),
        );
    }

    // ----------------------------------------------------------------
    // Contact screening
    // ----------------------------------------------------------------

    public function test_a_high_risk_country_contact_is_a_sanctions_hit_and_another_country_is_not(): void
    {
        $clean = $this->contact($this->organization->id, ['billing_country_code' => 'SA']);
        $listed = $this->contact($this->organization->id, ['billing_country_code' => 'ir']);

        $this->assertFalse($this->service->screenContact($clean)->sanctionsHit);

        $result = $this->service->screenContact($listed);
        $this->assertTrue($result->sanctionsHit);
        $this->assertFalse($result->pepHit);
        $this->assertSame('high_risk_country', $result->matchDetails[0]['type']);
    }

    public function test_a_pep_keyword_in_the_contacts_notes_is_a_pep_hit_and_ordinary_notes_are_not(): void
    {
        $plain = $this->contact($this->organization->id, ['notes' => 'Pays on time.']);
        $pep = $this->contact($this->organization->id, ['notes' => 'Brother of the Minister of Trade.']);

        $this->assertFalse($this->service->screenContact($plain)->pepHit);

        $result = $this->service->screenContact($pep);
        $this->assertTrue($result->pepHit);
        $this->assertFalse($result->sanctionsHit);
        $this->assertSame('minister', $result->matchDetails[0]['keyword']);
    }

    public function test_a_repeat_screening_reports_the_pep_hit_as_a_pep_hit(): void
    {
        // The cached row records what matched, so reading it back must not turn
        // a PEP keyword into a sanctions match or drop it altogether.
        $pep = $this->contact($this->organization->id, [
            'billing_country_code' => 'SA',
            'notes' => 'Brother of the Minister of Trade.',
        ]);

        $this->service->screenContact($pep);
        $again = $this->service->screenContact($pep);

        $this->assertTrue($again->fromCache);
        $this->assertTrue($again->pepHit);
        $this->assertFalse($again->sanctionsHit);
        $this->assertSame('pep_keyword', $again->matchDetails[0]['type']);
    }

    public function test_a_repeat_screening_reports_a_sanctions_hit_and_a_clean_contact_unchanged(): void
    {
        $listed = $this->contact($this->organization->id, ['billing_country_code' => 'SY', 'notes' => null]);
        $clean = $this->contact($this->organization->id, ['billing_country_code' => 'SA', 'notes' => null]);

        $this->service->screenContact($listed);
        $this->service->screenContact($clean);

        $listedAgain = $this->service->screenContact($listed);
        $this->assertTrue($listedAgain->fromCache);
        $this->assertTrue($listedAgain->sanctionsHit);
        $this->assertFalse($listedAgain->pepHit);

        $cleanAgain = $this->service->screenContact($clean);
        $this->assertTrue($cleanAgain->fromCache);
        $this->assertFalse($cleanAgain->sanctionsHit);
        $this->assertFalse($cleanAgain->pepHit);
    }

    public function test_changing_a_screened_field_forces_a_fresh_screening(): void
    {
        $contact = $this->contact($this->organization->id, ['billing_country_code' => 'SA', 'notes' => null]);

        $first = $this->service->screenContact($contact);
        $this->assertFalse($first->fromCache);
        $this->assertFalse($this->service->screenContact($contact)->fromCache === false);

        $contact->update(['billing_country_code' => 'KP']);

        $rescreened = $this->service->screenContact($contact->fresh());
        $this->assertFalse($rescreened->fromCache);
        $this->assertTrue($rescreened->sanctionsHit);
        $this->assertNotSame($first->dataHash, $rescreened->dataHash);
        $this->assertSame(1, DB::table('aml_screening_cache')->where('contact_id', $contact->id)->count());
    }

    // ----------------------------------------------------------------
    // Risk score
    // ----------------------------------------------------------------

    public function test_a_pep_contact_keeps_its_pep_hit_on_the_risk_score_the_job_writes(): void
    {
        // RunAmlScreeningJob screens the contact and then scores it, so the
        // score is always written from a cached screening result.
        $pep = $this->contact($this->organization->id, [
            'billing_country_code' => 'SA',
            'notes' => 'Former Minister of Finance.',
        ]);

        $this->service->screenContact($pep);
        $this->service->updateRiskScore($pep);

        $score = AmlRiskScore::withoutGlobalScopes()->where('contact_id', $pep->id)->sole();
        $this->assertTrue($score->pep_hit);
        $this->assertFalse($score->sanctions_hit);
        $this->assertSame(25, $score->score_breakdown['pep_hit']);
    }

    public function test_a_sanctioned_country_alone_scores_one_point_short_of_critical(): void
    {
        // Geographic risk 20 + sanctions 50 + no due diligence 10 lands on 80,
        // and 80 is the top of "high": the same country counted twice still
        // does not make a contact critical, and only a critical contact flags
        // its transactions. Listed for a decision on the bands.
        $listed = $this->contact($this->organization->id, ['billing_country_code' => 'IR', 'notes' => null]);

        $this->service->updateRiskScore($listed);

        $score = AmlRiskScore::withoutGlobalScopes()->where('contact_id', $listed->id)->sole();
        $this->assertSame(80, $score->score);
        $this->assertSame(AmlRiskScore::HIGH, $score->risk_level);
        $this->assertSame(20, $score->score_breakdown['geographic_risk']);
        $this->assertSame(50, $score->score_breakdown['sanctions_hit']);
        $this->assertTrue($score->sanctions_hit);
    }

    public function test_a_sanctioned_contact_who_is_also_a_pep_scores_critical(): void
    {
        $listed = $this->contact($this->organization->id, [
            'billing_country_code' => 'IR',
            'notes' => 'Former Minister of Finance.',
        ]);

        $this->service->updateRiskScore($listed);

        $score = AmlRiskScore::withoutGlobalScopes()->where('contact_id', $listed->id)->sole();
        $this->assertSame(100, $score->score);
        $this->assertSame(AmlRiskScore::CRITICAL, $score->risk_level);
    }

    public function test_an_unremarkable_contact_scores_only_for_its_missing_customer_due_diligence(): void
    {
        $this->service->updateRiskScore($this->contact);

        $score = AmlRiskScore::withoutGlobalScopes()->where('contact_id', $this->contact->id)->sole();
        $this->assertSame(10, $score->score);
        $this->assertSame(AmlRiskScore::LOW, $score->risk_level);
        $this->assertSame(10, $score->score_breakdown['kyc_status']);
    }

    public function test_a_completed_due_diligence_record_of_another_organization_does_not_clear_this_one(): void
    {
        $this->cddRecord($this->otherOrganization->id, $this->contact->id, 'completed');

        $this->service->updateRiskScore($this->contact);

        $score = AmlRiskScore::withoutGlobalScopes()->where('contact_id', $this->contact->id)->sole();
        $this->assertSame(10, $score->score_breakdown['kyc_status']);
    }

    public function test_this_organizations_completed_due_diligence_record_clears_the_kyc_points(): void
    {
        $this->cddRecord($this->organization->id, $this->contact->id, 'completed');

        $this->service->updateRiskScore($this->contact);

        $score = AmlRiskScore::withoutGlobalScopes()->where('contact_id', $this->contact->id)->sole();
        $this->assertSame(0, $score->score_breakdown['kyc_status']);
    }

    public function test_another_organizations_invoices_do_not_raise_this_contacts_risk(): void
    {
        Invoice::factory()->create([
            'organization_id' => $this->otherOrganization->id,
            'customer_id' => $this->contact->id,
            'total' => 50000,
            'status' => 'sent',
        ]);

        $this->service->updateRiskScore($this->contact);

        $breakdown = AmlRiskScore::withoutGlobalScopes()->where('contact_id', $this->contact->id)->sole()->score_breakdown;
        $this->assertSame(0, $breakdown['contact_age']);
        $this->assertSame(0, $breakdown['unpaid_invoices_ratio']);
    }

    public function test_this_organizations_invoices_do_raise_this_contacts_risk(): void
    {
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->contact->id,
            'total' => 50000,
            'status' => 'sent',
        ]);

        $this->service->updateRiskScore($this->contact);

        $breakdown = AmlRiskScore::withoutGlobalScopes()->where('contact_id', $this->contact->id)->sole()->score_breakdown;
        $this->assertSame(10, $breakdown['contact_age']);
        $this->assertSame(10, $breakdown['unpaid_invoices_ratio']);
    }

    public function test_another_organizations_payments_do_not_raise_this_contacts_velocity(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->payment($this->otherOrganization->id, $this->contact->id, 100);
        }

        $this->service->updateRiskScore($this->contact);

        $breakdown = AmlRiskScore::withoutGlobalScopes()->where('contact_id', $this->contact->id)->sole()->score_breakdown;
        $this->assertSame(0, $breakdown['transaction_velocity']);
    }

    public function test_this_organizations_payments_do_raise_this_contacts_velocity(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->payment($this->organization->id, $this->contact->id, 100);
        }

        $this->service->updateRiskScore($this->contact);

        $breakdown = AmlRiskScore::withoutGlobalScopes()->where('contact_id', $this->contact->id)->sole()->score_breakdown;
        $this->assertSame(10, $breakdown['transaction_velocity']);
    }

    public function test_rescoring_a_contact_replaces_its_score_rather_than_adding_a_second(): void
    {
        $this->service->updateRiskScore($this->contact);
        $this->cddRecord($this->organization->id, $this->contact->id, 'completed');
        $this->service->updateRiskScore($this->contact);

        $this->assertSame(1, AmlRiskScore::withoutGlobalScopes()->where('contact_id', $this->contact->id)->count());
        $this->assertSame(0, AmlRiskScore::withoutGlobalScopes()->where('contact_id', $this->contact->id)->sole()->score);
    }

    // ----------------------------------------------------------------
    // Suspicious activity reports
    // ----------------------------------------------------------------

    public function test_a_report_records_the_contacts_name_its_type_and_its_transactions(): void
    {
        $sar = $this->service->createSar(
            organizationId: $this->organization->id,
            contactId: $this->contact->id,
            activityType: AmlSuspiciousActivity::LAYERING,
            transactionIds: [4, 5],
            description: 'Funds moved through three accounts in a day.',
            createdBy: $this->user->id,
            reportType: AmlSuspiciousActivity::STR,
        );

        $this->assertSame('Gulf Traders', $sar->contact_name);
        $this->assertSame(AmlSuspiciousActivity::STR, $sar->report_type);
        $this->assertSame(AmlSuspiciousActivity::STATUS_DRAFT, $sar->status);
        $this->assertSame([4, 5], $sar->related_transaction_ids);
        $this->assertSame($this->user->id, $sar->created_by);
    }

    public function test_a_report_does_not_name_a_contact_of_another_organization(): void
    {
        $foreign = $this->contact($this->otherOrganization->id, ['company_name' => 'Foreign Holdings']);

        $sar = $this->service->createSar(
            organizationId: $this->organization->id,
            contactId: $foreign->id,
            activityType: AmlSuspiciousActivity::STRUCTURING,
            transactionIds: [1],
            description: 'Cross-tenant probe.',
            createdBy: $this->user->id,
        );

        $this->assertNull($sar->contact_name);
        $this->assertSame($this->organization->id, $sar->organization_id);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function screen(
        int $transactionId,
        float $amount,
        string $type = 'payment',
        string $currency = 'SAR',
        ?int $contactId = null,
    ): void {
        $this->service->screenTransaction($type, $transactionId, $amount, $currency, $this->organization->id, $contactId);
    }

    private function escalate(string $type, int $transactionId): void
    {
        (new RunAmlEscalationJob($type, $transactionId, $this->organization->id))->handle($this->service);
    }

    /** @return list<string> */
    private function flagReasons(): array
    {
        return AmlTransactionFlag::withoutGlobalScopes()
            ->orderBy('id')
            ->pluck('flag_reason')
            ->unique()
            ->values()
            ->all();
    }

    private function contact(int $organizationId, array $attributes = []): Contact
    {
        return Contact::factory()->create(array_merge([
            'organization_id' => $organizationId,
            'company_name' => 'Gulf Traders',
            'billing_country_code' => 'SA',
            'notes' => null,
        ], $attributes));
    }

    private function payment(int $organizationId, int $contactId, float|int $amount): int
    {
        return PaymentReceived::factory()->create([
            'organization_id' => $organizationId,
            'customer_id' => $contactId,
            'amount' => $amount,
            'base_amount' => $amount,
        ])->id;
    }

    private function invoiceFor(int $organizationId, int $contactId): int
    {
        return Invoice::factory()->create([
            'organization_id' => $organizationId,
            'customer_id' => $contactId,
        ])->id;
    }

    private function riskScore(int $organizationId, int $contactId, string $riskLevel): void
    {
        AmlRiskScore::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'contact_id' => $contactId,
            'score' => 90,
            'risk_level' => $riskLevel,
            'score_breakdown' => [],
            'sanctions_hit' => false,
            'pep_hit' => false,
        ]);
    }

    private function cddRecord(int $organizationId, int $contactId, string $status): void
    {
        DB::table('aml_cdd_records')->insert([
            'organization_id' => $organizationId,
            'contact_id' => $contactId,
            'cdd_level' => 'standard',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function userOf(int $organizationId): User
    {
        return User::factory()->create(['organization_id' => $organizationId]);
    }
}
