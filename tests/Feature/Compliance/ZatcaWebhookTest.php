<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The endpoint ZATCA calls back on.
 *
 * It takes no session and no token, it is reachable by anyone who can resolve
 * the host, and what it writes is whether an invoice was cleared by the tax
 * authority. It had no test.
 *
 * The organisation is read from the payload rather than from a logged-in
 * user, so the tenant check is part of what has to hold here.
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
            'compliance_uuid' => 'uuid-under-test',
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

    public function test_it_refuses_a_wrong_signature(): void
    {
        $body = $this->body('invoice.cleared', []);

        $this->call('POST', '/api/v1/webhooks/zatca', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'sha256='.str_repeat('0', 64),
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) time(),
        ], $body)->assertStatus(401);

        $this->assertSame(
            Invoice::COMPLIANCE_SUBMITTED,
            $this->invoice->refresh()->compliance_status
        );
    }

    public function test_it_refuses_a_stale_timestamp(): void
    {
        $body = $this->body('invoice.cleared', []);

        $this->call('POST', '/api/v1/webhooks/zatca', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $this->sign($body),
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) (time() - 3600),
        ], $body)->assertStatus(401);
    }

    public function test_it_refuses_without_the_headers(): void
    {
        $this->postJson('/api/v1/webhooks/zatca', ['event' => 'invoice.cleared'])
            ->assertStatus(400);
    }

    public function test_another_organisation_cannot_touch_the_invoice(): void
    {
        // The uuid is right; the organisation is not.
        $this->send('invoice.cleared', [], organizationId: $this->organization->id + 999)
            ->assertStatus(404);

        $this->assertSame(
            Invoice::COMPLIANCE_SUBMITTED,
            $this->invoice->refresh()->compliance_status
        );
    }

    public function test_a_submission_is_not_undone_by_a_replay(): void
    {
        Notification::fake();

        $this->send('invoice.cleared', [])->assertStatus(200);

        // Lower priority than cleared, so it is dropped as a downgrade.
        $this->send('invoice.issued', [])->assertStatus(200);

        $this->assertSame(
            Invoice::COMPLIANCE_CLEARED,
            $this->invoice->refresh()->compliance_status
        );
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

        $this->assertSame(
            Invoice::COMPLIANCE_REPORTED,
            $this->invoice->refresh()->compliance_status
        );
    }

    public function test_a_rejection_overrides_a_clearance_and_sticks(): void
    {
        Notification::fake();

        $this->send('invoice.cleared', [])->assertStatus(200);
        $this->send('invoice.rejected', ['errors' => ['bad']])->assertStatus(200);

        $this->assertSame(
            Invoice::COMPLIANCE_REJECTED,
            $this->invoice->refresh()->compliance_status
        );

        // And nothing puts it back: rejected outranks cleared, so a later
        // clearance is treated as a downgrade and dropped.
        $this->send('invoice.cleared', [])->assertStatus(200);

        $this->assertSame(
            Invoice::COMPLIANCE_REJECTED,
            $this->invoice->refresh()->compliance_status
        );
    }

    public function test_an_unknown_event_is_acknowledged(): void
    {
        // Answering anything else would have ZATCA retry it forever.
        $this->send('invoice.something-else', [])->assertStatus(200);
    }

    private function send(string $event, array $data, ?int $organizationId = null): TestResponse
    {
        $body = $this->body($event, $data, $organizationId);

        return $this->call('POST', '/api/v1/webhooks/zatca', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $this->sign($body),
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) time(),
        ], $body);
    }

    private function body(string $event, array $data, ?int $organizationId = null): string
    {
        return (string) json_encode([
            'event' => $event,
            'data' => array_merge([
                'invoice_uuid' => $this->invoice->compliance_uuid,
                'organization_id' => $organizationId ?? $this->organization->id,
            ], $data),
        ]);
    }

    private function sign(string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $body, self::SECRET);
    }
}
