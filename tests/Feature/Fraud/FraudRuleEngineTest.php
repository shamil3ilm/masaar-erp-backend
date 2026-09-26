<?php

declare(strict_types=1);

namespace Tests\Feature\Fraud;

use App\Models\Core\Organization;
use App\Models\Core\Role;
use App\Models\Fraud\FraudAlert;
use App\Models\Fraud\FraudRule;
use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\PaymentReceived;
use App\Models\User;
use App\Notifications\Fraud\FraudAlertNotification;
use App\Services\Fraud\EvaluationResult;
use App\Services\Fraud\FraudRuleEngine;
use App\Services\Fraud\FraudRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The detection logic behind a fraud alert: which rule fires on what, what it
 * does not fire on, and the alert it leaves behind.
 *
 * Every rule is proved in both directions — something over the line triggers it
 * and something under the line does not — because a rule that cannot fire is
 * indistinguishable from no rule at all.
 */
class FraudRuleEngineTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrganization;

    private User $otherUser;

    private FraudRuleEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->otherOrganization = Organization::factory()->create();
        $this->otherUser = User::factory()->create(['organization_id' => $this->otherOrganization->id]);

        $this->engine = app(FraudRuleEngine::class);
    }

    // ----------------------------------------------------------------
    // Rule selection
    // ----------------------------------------------------------------

    public function test_another_organizations_rule_is_never_applied(): void
    {
        $this->rule([
            'rule_type' => FraudRule::AMOUNT,
            'conditions' => ['field' => 'total', 'operator' => '>=', 'value' => 100],
        ], $this->otherOrganization->id);

        $result = $this->evaluateInvoice(['total' => 999999.0]);

        $this->assertFalse($result->flagged);
        $this->assertSame(0, FraudAlert::withoutGlobalScopes()->count());
    }

    public function test_an_inactive_rule_and_a_rule_for_another_entity_type_are_skipped(): void
    {
        $this->rule([
            'is_active' => false,
            'conditions' => ['field' => 'total', 'operator' => '>=', 'value' => 100],
        ]);
        $this->rule([
            'entity_type' => 'payment',
            'conditions' => ['field' => 'total', 'operator' => '>=', 'value' => 100],
        ]);

        $this->assertFalse($this->evaluateInvoice(['total' => 999999.0])->flagged);
    }

    public function test_an_unrecognised_rule_type_never_matches(): void
    {
        $this->rule(['rule_type' => 'clairvoyance', 'conditions' => ['field' => 'total']]);

        $this->assertFalse($this->evaluateInvoice(['total' => 999999.0])->flagged);
    }

    // ----------------------------------------------------------------
    // AMOUNT rules
    // ----------------------------------------------------------------

    public function test_an_amount_rule_fires_at_its_threshold_and_not_a_cent_below(): void
    {
        // The seeded "Large single transaction" template uses >=, so the
        // threshold itself is inside the rule.
        $this->rule(['conditions' => ['field' => 'total', 'operator' => '>=', 'value' => 50000]]);

        $this->assertTrue($this->evaluateInvoice(['total' => 50000.0])->flagged);

        FraudAlert::withoutGlobalScopes()->delete();

        $this->assertFalse($this->evaluateInvoice(['total' => 49999.99])->flagged);
        $this->assertSame(0, FraudAlert::withoutGlobalScopes()->count());
    }

    public function test_a_greater_than_amount_rule_excludes_its_threshold(): void
    {
        $this->rule(['conditions' => ['field' => 'total', 'operator' => '>', 'value' => 50000]]);

        $this->assertFalse($this->evaluateInvoice(['total' => 50000.0])->flagged);
        $this->assertTrue($this->evaluateInvoice(['total' => 50000.01])->flagged);
    }

    public function test_an_amount_rule_with_no_configured_value_flags_every_transaction(): void
    {
        // An absent "value" reads as 0, so ">= 0" matches anything. Recorded as
        // the behaviour that exists, not as the behaviour wanted.
        $this->rule(['conditions' => ['field' => 'total', 'operator' => '>=']]);

        $this->assertTrue($this->evaluateInvoice(['total' => 0.01])->flagged);
    }

    public function test_an_amount_rule_reading_a_field_the_entity_does_not_carry_never_fires(): void
    {
        // A missing field reads as 0, so a payment — which carries "amount",
        // never "total" — can never match a rule left on the default field.
        $this->rule([
            'entity_type' => 'payment',
            'conditions' => ['operator' => '>=', 'value' => 1000],
        ]);

        $result = $this->engine->evaluate('payment', ['id' => 1, 'amount' => 999999.0], $this->organization->id);

        $this->assertFalse($result->flagged);
    }

    public function test_an_amount_rule_with_an_unrecognised_operator_never_fires(): void
    {
        $this->rule(['conditions' => ['field' => 'total', 'operator' => '=>', 'value' => 1]]);

        $this->assertFalse($this->evaluateInvoice(['total' => 999999.0])->flagged);
    }

    // ----------------------------------------------------------------
    // VELOCITY rules
    // ----------------------------------------------------------------

    public function test_an_invoice_velocity_rule_fires_one_above_its_threshold_and_not_at_it(): void
    {
        // The comparison is strictly greater than, so a threshold of 2 needs a
        // third invoice. Which side of the line the threshold belongs on is a
        // business decision; this pins today's answer.
        $this->rule([
            'rule_type' => FraudRule::VELOCITY,
            'conditions' => ['metric' => 'invoice_count', 'window_minutes' => 60, 'threshold' => 2],
        ]);

        $this->invoices($this->organization->id, 2);
        $this->assertFalse($this->evaluateInvoice(['total' => 1.0])->flagged);

        $this->invoices($this->organization->id, 1);
        $this->assertTrue($this->evaluateInvoice(['total' => 1.0])->flagged);
    }

    public function test_another_organizations_invoices_do_not_feed_a_velocity_rule(): void
    {
        $this->rule([
            'rule_type' => FraudRule::VELOCITY,
            'conditions' => ['metric' => 'invoice_count', 'window_minutes' => 60, 'threshold' => 2],
        ]);

        $this->invoices($this->otherOrganization->id, 5);

        $this->assertFalse($this->evaluateInvoice(['total' => 1.0])->flagged);
    }

    public function test_a_velocity_rule_ignores_activity_older_than_its_window(): void
    {
        $this->rule([
            'rule_type' => FraudRule::VELOCITY,
            'conditions' => ['metric' => 'invoice_count', 'window_minutes' => 60, 'threshold' => 2],
        ]);

        $invoices = $this->invoices($this->organization->id, 3);
        DB::table('invoices')->whereIn('id', $invoices)->update(['created_at' => now()->subMinutes(61)]);

        $this->assertFalse($this->evaluateInvoice(['total' => 1.0])->flagged);
    }

    public function test_a_payment_velocity_rule_counts_this_organizations_payments_only(): void
    {
        $this->rule([
            'entity_type' => 'payment',
            'rule_type' => FraudRule::VELOCITY,
            'conditions' => ['metric' => 'payment_count', 'window_minutes' => 60, 'threshold' => 1],
        ]);

        $this->payments($this->otherOrganization->id, 3);
        $this->assertFalse($this->evaluatePayment(['amount' => 1.0])->flagged);

        $this->payments($this->organization->id, 2);
        $this->assertTrue($this->evaluatePayment(['amount' => 1.0])->flagged);
    }

    public function test_a_failed_login_velocity_rule_counts_only_the_given_email(): void
    {
        $this->rule([
            'entity_type' => 'login',
            'rule_type' => FraudRule::VELOCITY,
            'conditions' => ['metric' => 'failed_login_count', 'window_minutes' => 15, 'threshold' => 2],
        ]);

        $this->loginAttempts('someone.else@example.test', 5, false);
        $this->assertFalse($this->evaluateLogin(['email' => 'target@example.test'])->flagged);

        $this->loginAttempts('target@example.test', 2, false);
        $this->assertFalse($this->evaluateLogin(['email' => 'target@example.test'])->flagged);

        $this->loginAttempts('target@example.test', 1, false);
        $this->assertTrue($this->evaluateLogin(['email' => 'target@example.test'])->flagged);
    }

    public function test_a_failed_login_velocity_rule_ignores_successful_attempts(): void
    {
        $this->rule([
            'entity_type' => 'login',
            'rule_type' => FraudRule::VELOCITY,
            'conditions' => ['metric' => 'failed_login_count', 'window_minutes' => 15, 'threshold' => 2],
        ]);

        $this->loginAttempts('target@example.test', 5, true);

        $this->assertFalse($this->evaluateLogin(['email' => 'target@example.test'])->flagged);
    }

    public function test_a_velocity_rule_without_a_metric_never_fires(): void
    {
        $this->rule([
            'rule_type' => FraudRule::VELOCITY,
            'conditions' => ['window_minutes' => 60, 'threshold' => 0],
        ]);

        $this->invoices($this->organization->id, 3);

        $this->assertFalse($this->evaluateInvoice(['total' => 1.0])->flagged);
    }

    // ----------------------------------------------------------------
    // PATTERN rules — structuring
    // ----------------------------------------------------------------

    public function test_structuring_fires_on_the_third_payment_inside_the_band(): void
    {
        // A threshold of 9000 puts the band at 7200.00 – 8999.99, and three
        // payments inside it are the pattern.
        $contact = $this->contact($this->organization->id);
        $this->structuringRule();

        $this->payment($this->organization->id, $contact->id, 8500);
        $this->payment($this->organization->id, $contact->id, 8500);
        $this->assertFalse($this->evaluatePayment(['amount' => 8500.0, 'contact_id' => $contact->id])->flagged);

        $this->payment($this->organization->id, $contact->id, 8500);
        $this->assertTrue($this->evaluatePayment(['amount' => 8500.0, 'contact_id' => $contact->id])->flagged);
    }

    public function test_structuring_ignores_payments_at_the_threshold_and_below_the_band(): void
    {
        $contact = $this->contact($this->organization->id);
        $this->structuringRule();

        $this->payment($this->organization->id, $contact->id, 9000);    // at the threshold — outside
        $this->payment($this->organization->id, $contact->id, 7199.99); // under the band — outside
        $this->payment($this->organization->id, $contact->id, 8999.99); // top of the band — inside
        $this->assertFalse($this->evaluatePayment(['amount' => 1.0, 'contact_id' => $contact->id])->flagged);

        $this->payment($this->organization->id, $contact->id, 7200);    // floor of the band — inside
        $this->payment($this->organization->id, $contact->id, 8000);
        $this->assertTrue($this->evaluatePayment(['amount' => 1.0, 'contact_id' => $contact->id])->flagged);
    }

    public function test_structuring_ignores_another_organizations_payments(): void
    {
        $contact = $this->contact($this->organization->id);
        $this->structuringRule();

        for ($i = 0; $i < 4; $i++) {
            $this->payment($this->otherOrganization->id, $contact->id, 8500);
        }

        $this->assertFalse($this->evaluatePayment(['amount' => 8500.0, 'contact_id' => $contact->id])->flagged);
    }

    public function test_structuring_ignores_payments_outside_the_window_and_of_other_contacts(): void
    {
        $contact = $this->contact($this->organization->id);
        $other = $this->contact($this->organization->id);
        $this->structuringRule();

        $stale = [
            $this->payment($this->organization->id, $contact->id, 8500),
            $this->payment($this->organization->id, $contact->id, 8500),
        ];
        DB::table('payments_received')->whereIn('id', $stale)->update(['created_at' => now()->subDays(8)]);
        $this->payment($this->organization->id, $other->id, 8500);
        $this->payment($this->organization->id, $contact->id, 8500);

        $this->assertFalse($this->evaluatePayment(['amount' => 8500.0, 'contact_id' => $contact->id])->flagged);
    }

    public function test_structuring_does_not_fire_without_a_contact(): void
    {
        $this->structuringRule();

        $this->assertFalse($this->evaluatePayment(['amount' => 8500.0])->flagged);
    }

    // ----------------------------------------------------------------
    // PATTERN rules — round amount and rapid payment
    // ----------------------------------------------------------------

    public function test_round_amount_fires_on_a_multiple_of_a_thousand_at_the_minimum(): void
    {
        $this->rule([
            'entity_type' => 'payment',
            'rule_type' => FraudRule::PATTERN,
            'conditions' => ['pattern' => 'round_amount', 'min_amount' => 10000],
        ]);

        $this->assertTrue($this->evaluatePayment(['amount' => 10000.0])->flagged);
        $this->assertFalse($this->evaluatePayment(['amount' => 9999.99])->flagged);
    }

    public function test_round_amount_means_a_multiple_of_a_thousand_not_a_whole_amount(): void
    {
        // 10500.00 has no fractional part but is not a multiple of 1000, so it
        // does not match. Which step counts as "round" is a business decision.
        $this->rule([
            'entity_type' => 'payment',
            'rule_type' => FraudRule::PATTERN,
            'conditions' => ['pattern' => 'round_amount', 'min_amount' => 10000],
        ]);

        $this->assertFalse($this->evaluatePayment(['amount' => 10500.0])->flagged);
        $this->assertTrue($this->evaluatePayment(['amount' => 11000.0])->flagged);
    }

    public function test_rapid_payment_fires_only_on_a_fresh_invoice_for_the_same_contact(): void
    {
        $contact = $this->contact($this->organization->id);
        $other = $this->contact($this->organization->id);
        $this->rapidPaymentRule();

        $this->invoiceFor($this->organization->id, $other->id);
        $this->assertFalse($this->evaluatePayment(['amount' => 9000.0, 'contact_id' => $contact->id])->flagged);

        $this->invoiceFor($this->organization->id, $contact->id);
        $this->assertTrue($this->evaluatePayment(['amount' => 9000.0, 'contact_id' => $contact->id])->flagged);
    }

    public function test_rapid_payment_ignores_another_organizations_invoice_and_amounts_below_the_minimum(): void
    {
        $contact = $this->contact($this->organization->id);
        $this->rapidPaymentRule();

        $this->invoiceFor($this->otherOrganization->id, $contact->id);
        $this->assertFalse($this->evaluatePayment(['amount' => 9000.0, 'contact_id' => $contact->id])->flagged);

        $this->invoiceFor($this->organization->id, $contact->id);
        $this->assertFalse($this->evaluatePayment(['amount' => 4999.99, 'contact_id' => $contact->id])->flagged);
        $this->assertTrue($this->evaluatePayment(['amount' => 5000.0, 'contact_id' => $contact->id])->flagged);
    }

    public function test_an_unrecognised_pattern_never_fires(): void
    {
        $this->rule([
            'entity_type' => 'payment',
            'rule_type' => FraudRule::PATTERN,
            'conditions' => ['pattern' => 'smurfing'],
        ]);

        $this->assertFalse($this->evaluatePayment(['amount' => 500000.0])->flagged);
    }

    // ----------------------------------------------------------------
    // BEHAVIORAL rules
    // ----------------------------------------------------------------

    public function test_new_ip_login_fires_for_an_unseen_address_and_not_for_a_known_one(): void
    {
        $this->behavioralRule('new_ip_login');

        $this->assertTrue($this->evaluateLogin(['user_id' => $this->user->id, 'ip_address' => '203.0.113.9'])->flagged);

        $this->userEvent($this->user->id, $this->organization->id, '203.0.113.9');

        $this->assertFalse($this->evaluateLogin(['user_id' => $this->user->id, 'ip_address' => '203.0.113.9'])->flagged);
    }

    public function test_new_ip_login_treats_an_address_last_seen_over_thirty_days_ago_as_new(): void
    {
        $this->behavioralRule('new_ip_login');

        $event = $this->userEvent($this->user->id, $this->organization->id, '203.0.113.9');
        DB::table('user_events')->where('id', $event)->update(['created_at' => now()->subDays(31)]);

        $this->assertTrue($this->evaluateLogin(['user_id' => $this->user->id, 'ip_address' => '203.0.113.9'])->flagged);
    }

    public function test_new_ip_login_does_not_fire_without_a_user_or_an_address(): void
    {
        $this->behavioralRule('new_ip_login');

        $this->assertFalse($this->evaluateLogin(['ip_address' => '203.0.113.9'])->flagged);
        $this->assertFalse($this->evaluateLogin(['user_id' => $this->user->id])->flagged);
    }

    public function test_high_value_after_info_change_uses_its_own_threshold_and_not_the_rules(): void
    {
        // The 10,000 line is written into the engine; a "threshold" in the
        // rule's conditions is read by nothing. Listed as a finding, pinned
        // here as the behaviour that exists today.
        $this->rule([
            'rule_type' => FraudRule::BEHAVIORAL,
            'conditions' => ['behavior' => 'high_value_after_info_change', 'threshold' => 100],
        ]);

        $this->auditLog($this->user->id);

        $this->assertFalse($this->evaluateInvoice(['user_id' => $this->user->id, 'total' => 9999.99])->flagged);
        $this->assertTrue($this->evaluateInvoice(['user_id' => $this->user->id, 'total' => 10000.0])->flagged);
    }

    public function test_high_value_after_info_change_needs_a_profile_change_inside_a_day(): void
    {
        $this->rule([
            'rule_type' => FraudRule::BEHAVIORAL,
            'conditions' => ['behavior' => 'high_value_after_info_change'],
        ]);

        $this->assertFalse($this->evaluateInvoice(['user_id' => $this->user->id, 'total' => 50000.0])->flagged);

        $log = $this->auditLog($this->user->id);
        DB::table('audit_logs')->where('id', $log)->update(['created_at' => now()->subHours(25)]);
        $this->assertFalse($this->evaluateInvoice(['user_id' => $this->user->id, 'total' => 50000.0])->flagged);

        DB::table('audit_logs')->where('id', $log)->update(['created_at' => now()->subHours(23)]);
        $this->assertTrue($this->evaluateInvoice(['user_id' => $this->user->id, 'total' => 50000.0])->flagged);
    }

    public function test_an_unrecognised_behavior_never_fires(): void
    {
        $this->behavioralRule('sleepwalking');

        $this->assertFalse($this->evaluateLogin(['user_id' => $this->user->id, 'ip_address' => '203.0.113.9'])->flagged);
    }

    // ----------------------------------------------------------------
    // GEOGRAPHIC rules
    // ----------------------------------------------------------------

    public function test_the_new_country_login_rule_only_fires_for_a_high_risk_country(): void
    {
        // The seeded "Login from new country" rule compares against a fixed
        // high-risk list and never looks at where this user logged in before,
        // so a first-ever login from GB passes. Listed as a finding.
        $this->rule([
            'entity_type' => 'login',
            'rule_type' => FraudRule::GEOGRAPHIC,
            'conditions' => ['behavior' => 'new_country_login'],
        ]);

        $this->assertFalse($this->evaluateLogin(['user_id' => $this->user->id, 'country_code' => 'GB'])->flagged);
        $this->assertTrue($this->evaluateLogin(['user_id' => $this->user->id, 'country_code' => 'IR'])->flagged);
    }

    public function test_an_allowed_country_list_fires_for_a_country_outside_it(): void
    {
        $this->rule([
            'rule_type' => FraudRule::GEOGRAPHIC,
            'conditions' => ['allowed_countries' => ['SA', 'AE']],
        ]);

        $this->assertFalse($this->evaluateInvoice(['total' => 1.0, 'billing_country_code' => 'SA'])->flagged);
        $this->assertTrue($this->evaluateInvoice(['total' => 1.0, 'billing_country_code' => 'GB'])->flagged);
    }

    public function test_a_geographic_rule_passes_a_transaction_whose_country_is_unknown(): void
    {
        // No country on the entity means no check at all — an unknown origin is
        // treated as an allowed one. Listed as a finding.
        $this->rule([
            'rule_type' => FraudRule::GEOGRAPHIC,
            'conditions' => ['allowed_countries' => ['SA'], 'block_high_risk' => true],
        ]);

        $this->assertFalse($this->evaluateInvoice(['total' => 1.0])->flagged);
        $this->assertFalse($this->evaluateInvoice(['total' => 1.0, 'billing_country_code' => null])->flagged);
    }

    public function test_block_high_risk_fires_on_a_listed_country_only(): void
    {
        $this->rule([
            'rule_type' => FraudRule::GEOGRAPHIC,
            'conditions' => ['block_high_risk' => true],
        ]);

        $this->assertFalse($this->evaluateInvoice(['total' => 1.0, 'country_code' => 'SA'])->flagged);
        $this->assertTrue($this->evaluateInvoice(['total' => 1.0, 'country_code' => 'KP'])->flagged);
    }

    public function test_a_geographic_rule_configured_with_nothing_never_fires(): void
    {
        // Neither an allowed list nor the high-risk switch: the rule exists and
        // checks nothing. Listed as a finding.
        $this->rule(['rule_type' => FraudRule::GEOGRAPHIC, 'conditions' => []]);

        $this->assertFalse($this->evaluateInvoice(['total' => 1.0, 'country_code' => 'KP'])->flagged);
    }

    // ----------------------------------------------------------------
    // The alert a trigger leaves behind
    // ----------------------------------------------------------------

    public function test_a_trigger_writes_one_alert_carrying_the_rule_the_entity_and_the_score(): void
    {
        $contact = $this->contact($this->organization->id);
        $rule = $this->rule([
            'conditions' => ['field' => 'total', 'operator' => '>=', 'value' => 50000],
            'severity' => FraudRule::MEDIUM,
            'score_impact' => 20,
        ]);

        $result = $this->engine->evaluate('invoice', [
            'id' => 77,
            'uuid' => 'a5b2c3d4-0000-4000-8000-000000000001',
            'total' => 60000.0,
            'contact_id' => $contact->id,
            'user_id' => $this->user->id,
            'ip_address' => '203.0.113.5',
        ], $this->organization->id);

        $this->assertTrue($result->flagged);
        $this->assertSame(20, $result->totalScore);
        $this->assertSame(FraudRule::MEDIUM, $result->highestSeverity);
        $this->assertFalse($result->shouldBlock);

        $alert = FraudAlert::withoutGlobalScopes()->sole();
        $this->assertSame($this->organization->id, $alert->organization_id);
        $this->assertSame($rule->id, $alert->fraud_rule_id);
        $this->assertSame('invoice', $alert->entity_type);
        $this->assertSame(77, $alert->entity_id);
        $this->assertSame('a5b2c3d4-0000-4000-8000-000000000001', $alert->entity_uuid);
        $this->assertSame($contact->id, $alert->contact_id);
        $this->assertSame($this->user->id, $alert->user_id);
        $this->assertSame(FraudAlert::OPEN, $alert->status);
        $this->assertSame(FraudRule::MEDIUM, $alert->severity);
        $this->assertSame(20, $alert->fraud_score);
        $this->assertSame('203.0.113.5', $alert->ip_address);
        $this->assertSame(20, $alert->evidence['total_score']);
        $this->assertEquals(60000.0, $alert->evidence['entity_data']['total']);
    }

    public function test_two_matching_rules_add_their_scores_and_each_write_their_own_alert(): void
    {
        $this->rule([
            'conditions' => ['field' => 'total', 'operator' => '>=', 'value' => 50000],
            'severity' => FraudRule::LOW,
            'score_impact' => 20,
        ]);
        $this->rule([
            'conditions' => ['field' => 'total', 'operator' => '>=', 'value' => 100000],
            'severity' => FraudRule::CRITICAL,
            'score_impact' => 50,
            'auto_block' => true,
        ]);

        $result = $this->evaluateInvoice(['total' => 200000.0]);

        $this->assertSame(70, $result->totalScore);
        $this->assertSame(FraudRule::CRITICAL, $result->highestSeverity);
        $this->assertTrue($result->shouldBlock);
        $this->assertCount(2, $result->triggeredRules);
        $this->assertSame(2, FraudAlert::withoutGlobalScopes()->count());
    }

    public function test_a_matching_rule_that_carries_no_score_still_raises_its_alert(): void
    {
        // A rule is a control, not only a scoring input: when it matched, its
        // alert is written even though it contributes nothing to the score.
        $rule = $this->rule([
            'conditions' => ['field' => 'total', 'operator' => '>=', 'value' => 1000],
            'score_impact' => 0,
            'auto_block' => true,
        ]);

        $result = $this->evaluateInvoice(['total' => 5000.0]);

        $this->assertTrue($result->flagged);
        $this->assertTrue($result->shouldBlock);
        $this->assertSame($rule->id, FraudAlert::withoutGlobalScopes()->sole()->fraud_rule_id);
    }

    public function test_evaluating_the_same_entity_twice_writes_the_alert_twice(): void
    {
        // Nothing de-duplicates: a re-run of the same check on the same entity
        // leaves a second, identical alert. Listed as a finding.
        $this->rule(['conditions' => ['field' => 'total', 'operator' => '>=', 'value' => 1000]]);

        $this->engine->evaluate('invoice', ['id' => 9, 'total' => 5000.0], $this->organization->id);
        $this->engine->evaluate('invoice', ['id' => 9, 'total' => 5000.0], $this->organization->id);

        $this->assertSame(2, FraudAlert::withoutGlobalScopes()->where('entity_id', 9)->count());
    }

    public function test_a_high_severity_alert_notifies_this_organizations_admins_only(): void
    {
        Notification::fake();

        $admin = $this->admin($this->organization->id);
        $foreignAdmin = $this->admin($this->otherOrganization->id);

        $this->rule([
            'conditions' => ['field' => 'total', 'operator' => '>=', 'value' => 1000],
            'severity' => FraudRule::HIGH,
        ]);

        $this->evaluateInvoice(['total' => 5000.0]);

        Notification::assertSentTo($admin, FraudAlertNotification::class);
        Notification::assertNotSentTo($foreignAdmin, FraudAlertNotification::class);
        Notification::assertNotSentTo($this->user, FraudAlertNotification::class);
    }

    public function test_an_alert_below_high_severity_notifies_nobody(): void
    {
        Notification::fake();

        $admin = $this->admin($this->organization->id);

        $this->rule([
            'conditions' => ['field' => 'total', 'operator' => '>=', 'value' => 1000],
            'severity' => FraudRule::MEDIUM,
        ]);

        $this->evaluateInvoice(['total' => 5000.0]);

        Notification::assertNotSentTo($admin, FraudAlertNotification::class);
    }

    public function test_a_broken_rule_leaves_the_entity_unflagged_rather_than_failing_the_caller(): void
    {
        // The engine is documented never to throw: a check must not take a
        // transaction down with it.
        $this->rule(['rule_type' => FraudRule::VELOCITY, 'conditions' => ['metric' => ['not', 'a', 'metric']]]);

        $result = $this->evaluateInvoice(['total' => 1.0]);

        $this->assertFalse($result->flagged);
        $this->assertSame(0, FraudAlert::withoutGlobalScopes()->count());
    }

    // ----------------------------------------------------------------
    // The rules an organization is given to start with
    // ----------------------------------------------------------------

    public function test_each_seeded_default_rule_fires_on_the_activity_it_names(): void
    {
        // A shipped default that cannot fire is a control an organization
        // believes it has. Each one is put in front of the activity it
        // describes, and named by the alert it raises.
        app(FraudRuleService::class)->seedDefaults($this->organization->id, $this->user->id);

        $contact = $this->contact($this->organization->id);

        $this->engine->evaluate('invoice', ['id' => 1, 'total' => 50000.0], $this->organization->id);
        $this->assertSame(['Large single transaction'], $this->alertedRuleNames());

        Invoice::factory()->count(21)->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $contact->id,
        ]);
        $this->engine->evaluate('invoice', ['id' => 2, 'total' => 1.0], $this->organization->id);
        $this->assertSame(['High velocity invoices'], $this->alertedRuleNames());

        for ($i = 0; $i < 3; $i++) {
            $this->payment($this->organization->id, $contact->id, 8500);
        }
        $this->engine->evaluate('payment', ['id' => 3, 'amount' => 8500.0, 'contact_id' => $contact->id], $this->organization->id);
        $this->assertSame(['Structuring pattern'], $this->alertedRuleNames());

        $this->engine->evaluate('payment', ['id' => 4, 'amount' => 10000.0], $this->organization->id);
        $this->assertSame(['Round-amount transaction'], $this->alertedRuleNames());

        $this->loginAttempts('target@example.test', 6, false);
        $this->engine->evaluate('login', ['id' => 5, 'email' => 'target@example.test', 'country_code' => 'SA'], $this->organization->id);
        $this->assertSame(['Multiple failed logins'], $this->alertedRuleNames());

        $this->engine->evaluate('login', ['id' => 6, 'email' => 'clean@example.test', 'country_code' => 'IR'], $this->organization->id);
        $this->assertSame(['Login from new country'], $this->alertedRuleNames());
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * The rules named by the alerts raised since the last check, clearing them
     * so each step of a walk-through reads only its own.
     *
     * @return list<string>
     */
    private function alertedRuleNames(): array
    {
        $names = FraudAlert::withoutGlobalScopes()
            ->with('rule:id,name')
            ->get()
            ->map(fn (FraudAlert $alert): string => $alert->rule->name)
            ->sort()
            ->values()
            ->all();

        FraudAlert::withoutGlobalScopes()->delete();

        return $names;
    }

    private function rule(array $attributes = [], ?int $organizationId = null): FraudRule
    {
        $organizationId ??= $this->organization->id;

        return FraudRule::withoutGlobalScopes()->create(array_merge([
            'organization_id' => $organizationId,
            'name' => 'Rule '.Str::random(8),
            'rule_type' => FraudRule::AMOUNT,
            'entity_type' => 'invoice',
            'conditions' => [],
            'severity' => FraudRule::MEDIUM,
            'is_active' => true,
            'auto_block' => false,
            'score_impact' => 10,
            'created_by' => $organizationId === $this->organization->id ? $this->user->id : $this->otherUser->id,
        ], $attributes));
    }

    private function structuringRule(): FraudRule
    {
        return $this->rule([
            'entity_type' => 'payment',
            'rule_type' => FraudRule::PATTERN,
            'conditions' => ['pattern' => 'structuring', 'threshold' => 9000, 'window_days' => 7],
        ]);
    }

    private function rapidPaymentRule(): FraudRule
    {
        return $this->rule([
            'entity_type' => 'payment',
            'rule_type' => FraudRule::PATTERN,
            'conditions' => ['pattern' => 'rapid_payment', 'min_amount' => 5000],
        ]);
    }

    private function behavioralRule(string $behavior): FraudRule
    {
        return $this->rule([
            'entity_type' => 'login',
            'rule_type' => FraudRule::BEHAVIORAL,
            'conditions' => ['behavior' => $behavior],
        ]);
    }

    private function evaluateInvoice(array $entityData): EvaluationResult
    {
        return $this->engine->evaluate('invoice', array_merge(['id' => 1], $entityData), $this->organization->id);
    }

    private function evaluatePayment(array $entityData): EvaluationResult
    {
        return $this->engine->evaluate('payment', array_merge(['id' => 1], $entityData), $this->organization->id);
    }

    private function evaluateLogin(array $entityData): EvaluationResult
    {
        return $this->engine->evaluate('login', array_merge(['id' => 1], $entityData), $this->organization->id);
    }

    private function contact(int $organizationId): Contact
    {
        return Contact::factory()->create([
            'organization_id' => $organizationId,
            'billing_country_code' => 'SA',
            'notes' => null,
        ]);
    }

    /** @return list<int> */
    private function invoices(int $organizationId, int $count): array
    {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $ids[] = $this->invoiceFor($organizationId, $this->contact($organizationId)->id);
        }

        return $ids;
    }

    private function invoiceFor(int $organizationId, int $contactId): int
    {
        return Invoice::factory()->create([
            'organization_id' => $organizationId,
            'customer_id' => $contactId,
        ])->id;
    }

    private function payments(int $organizationId, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->payment($organizationId, $this->contact($organizationId)->id, 100);
        }
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

    private function loginAttempts(string $email, int $count, bool $successful): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table('login_attempts')->insert([
                'email' => $email,
                'ip_address' => '203.0.113.1',
                'successful' => $successful,
                'attempted_at' => now(),
            ]);
        }
    }

    private function userEvent(int $userId, int $organizationId, string $ipAddress): int
    {
        return DB::table('user_events')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'event_type' => 'user_login',
            'payload' => json_encode([]),
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }

    private function auditLog(int $userId): int
    {
        return DB::table('audit_logs')->insertGetId([
            'organization_id' => $this->organization->id,
            'user_id' => $userId,
            'auditable_type' => User::class,
            'auditable_id' => $userId,
            'event' => 'updated',
            'created_at' => now(),
        ]);
    }

    private function admin(int $organizationId): User
    {
        $role = Role::factory()->create([
            'organization_id' => $organizationId,
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        $user = User::factory()->create(['organization_id' => $organizationId, 'is_active' => true]);
        $user->roles()->attach($role->id);

        return $user;
    }
}
