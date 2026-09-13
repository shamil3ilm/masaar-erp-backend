<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The endpoint the compliance platform calls back on.
 *
 * It takes no session and no token, it is reachable by anyone who can resolve
 * the host, and what it writes is whether an invoice was cleared by the tax
 * authority.
 *
 * The bodies here are the platform's own shape - id, event, timestamp, data -
 * because the earlier ones were not, and a receiver that passed against an
 * invented payload refused every real delivery.
 */
class ZatcaWebhookTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const SECRET = 'webhook-test-secret';

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        config(['zatca-integration.webhook_secret' => self::SECRET]);

        $this->setUpOrganization('SA');

        $customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => Contact::TYPE_CUSTOMER,
        ]);

        $this->invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'compliance_uuid' => 'platform-invoice-id',
            'compliance_status' => Invoice::COMPLIANCE_SUBMITTED,
        ]);
    }

    public function test_a_cleared_event_records_the_clearance(): void
    {
        Notification::fake();

        $this->send('invoice.cleared', [
            'hash' => 'the-hash',
            'qr_code' => 'the-qr',
        ])->assertStatus(200);

        $this->invoice->refresh();

        $this->assertSame(Invoice::COMPLIANCE_CLEARED, $this->invoice->compliance_status);
        $this->assertSame('the-hash', $this->invoice->compliance_hash);
        $this->assertSame('the-qr', $this->invoice->compliance_qr_code);
    }

    /**
     * The platform sends this header as ISO-8601. It is unsigned, so nothing
     * rests on it; demanding epoch seconds here refused every real delivery.
     */
    public function test_a_delivery_is_accepted_whatever_the_timestamp_header_says(): void
    {
        Notification::fake();

        $this->deliver($this->body('invoice.cleared', []), 'not a time at all')->assertStatus(200);

        $this->assertSame(Invoice::COMPLIANCE_CLEARED, $this->invoice->refresh()->compliance_status);
    }

    public function test_it_refuses_a_wrong_signature(): void
    {
        $this->deliver($this->body('invoice.cleared', []), signature: 'sha256='.str_repeat('0', 64))
            ->assertStatus(401);

        $this->assertSame(Invoice::COMPLIANCE_SUBMITTED, $this->invoice->refresh()->compliance_status);
    }

    public function test_it_refuses_a_delivery_signed_an_hour_ago(): void
    {
        $body = $this->body('invoice.cleared', [], timestamp: now()->subHour()->toISOString());

        $this->deliver($body)->assertStatus(401);

        $this->assertSame(Invoice::COMPLIANCE_SUBMITTED, $this->invoice->refresh()->compliance_status);
    }

    public function test_it_refuses_without_a_signature(): void
    {
        $this->postJson('/api/v1/webhooks/zatca', ['event' => 'invoice.cleared'])
            ->assertStatus(400);
    }

    /**
     * A captured request cannot be used twice.
     *
     * The signature covers the body, and the body carries the delivery's id
     * and timestamp. Within the window the id gives a repeat away; after it,
     * the signed timestamp does.
     */
    public function test_a_captured_delivery_is_not_processed_again(): void
    {
        Notification::fake();

        $body = $this->body('invoice.cleared', []);

        $this->deliver($body)->assertStatus(200)->assertJsonPath('message', 'Event processed');

        // The same bytes and signature, straight away: acknowledged, not rerun.
        $this->deliver($body)->assertStatus(200)->assertJsonPath('message', 'Already processed');

        // And an hour later, with a current header: the signed timestamp is stale.
        $this->travel(1)->hours();

        $this->deliver($body, now()->toISOString())->assertStatus(401);
    }

    /**
     * Credit notes are submitted too and are not invoices. Refusing their
     * events counts as a failed delivery, and ten of those disable the whole
     * subscription.
     */
    public function test_an_event_for_an_unknown_document_is_acknowledged_and_changes_nothing(): void
    {
        $this->send('invoice.cleared', [], invoiceId: 'someone-elses-document')->assertStatus(200);

        $this->assertSame(Invoice::COMPLIANCE_SUBMITTED, $this->invoice->refresh()->compliance_status);
    }

    public function test_an_event_without_an_invoice_id_is_acknowledged(): void
    {
        $body = (string) json_encode([
            'id' => (string) Str::uuid(),
            'event' => 'invoice.cleared',
            'timestamp' => now()->toISOString(),
            'data' => ['org_id' => 'platform-org'],
        ]);

        $this->deliver($body)->assertStatus(200);
    }

    public function test_a_submission_is_not_undone_by_a_lower_priority_event(): void
    {
        Notification::fake();

        $this->send('invoice.cleared', [])->assertStatus(200);

        // Lower priority than cleared, so it is dropped as a downgrade.
        $this->send('invoice.issued', [])->assertStatus(200);

        $this->assertSame(Invoice::COMPLIANCE_CLEARED, $this->invoice->refresh()->compliance_status);
    }

    public function test_reported_and_cleared_rank_equally(): void
    {
        Notification::fake();

        // Standard invoices are cleared and simplified ones are reported, so
        // an invoice is only ever one of the two and the pair never race. They
        // carry the same priority, and equal is not a downgrade, so whichever
        // arrives last is the one kept.
        $this->send('invoice.cleared', [])->assertStatus(200);
        $this->send('invoice.reported', [])->assertStatus(200);

        $this->assertSame(Invoice::COMPLIANCE_REPORTED, $this->invoice->refresh()->compliance_status);
    }

    public function test_a_rejection_overrides_a_clearance_and_sticks(): void
    {
        Notification::fake();

        $this->send('invoice.cleared', [])->assertStatus(200);
        $this->send('invoice.rejected', ['errors' => ['bad']])->assertStatus(200);

        $this->assertSame(Invoice::COMPLIANCE_REJECTED, $this->invoice->refresh()->compliance_status);

        // And nothing puts it back: rejected outranks cleared, so a later
        // clearance is treated as a downgrade and dropped.
        $this->send('invoice.cleared', [])->assertStatus(200);

        $this->assertSame(Invoice::COMPLIANCE_REJECTED, $this->invoice->refresh()->compliance_status);
    }

    public function test_an_unknown_event_is_acknowledged(): void
    {
        // Answering anything else would count as a failed delivery.
        $this->send('invoice.something-else', [])->assertStatus(200);
    }

    private function send(string $event, array $data, ?string $invoiceId = null): TestResponse
    {
        return $this->deliver($this->body($event, $data, $invoiceId));
    }

    private function deliver(string $body, ?string $timestampHeader = null, ?string $signature = null): TestResponse
    {
        return $this->call('POST', '/api/v1/webhooks/zatca', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature ?? 'sha256='.hash_hmac('sha256', $body, self::SECRET),
            'HTTP_X_WEBHOOK_TIMESTAMP' => $timestampHeader ?? now()->toISOString(),
        ], $body);
    }

    /** A body in the platform's shape, with a fresh delivery id. */
    private function body(string $event, array $data, ?string $invoiceId = null, ?string $timestamp = null): string
    {
        return (string) json_encode([
            'id' => (string) Str::uuid(),
            'event' => $event,
            'timestamp' => $timestamp ?? now()->toISOString(),
            'data' => array_merge([
                'invoice_id' => $invoiceId ?? $this->invoice->compliance_uuid,
                'org_id' => 'platform-org',
            ], $data),
        ]);
    }
}
