<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Sales\Contact;
use App\Models\Sales\Invoice;
use App\Notifications\Sales\InvoiceOverdueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Covers the invoices:mark-overdue command: which invoices move to overdue,
 * and when the customer is told.
 */
class MarkOverdueInvoicesTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        Notification::fake();
    }

    private function customer(): Contact
    {
        return Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'email'           => 'customer@example.com',
        ]);
    }

    private function pastDueInvoice(string $status = Invoice::STATUS_SENT): Invoice
    {
        return Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id'     => $this->customer()->id,
            'status'          => $status,
            'due_date'        => now()->subDays(10),
        ]);
    }

    public function test_past_due_sent_invoice_becomes_overdue(): void
    {
        $invoice = $this->pastDueInvoice();

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame(Invoice::STATUS_OVERDUE, $invoice->fresh()->status);
    }

    public function test_past_due_partial_invoice_becomes_overdue(): void
    {
        $invoice = $this->pastDueInvoice(Invoice::STATUS_PARTIAL);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame(Invoice::STATUS_OVERDUE, $invoice->fresh()->status);
    }

    public function test_invoice_not_yet_due_is_left_alone(): void
    {
        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id'     => $this->customer()->id,
            'status'          => Invoice::STATUS_SENT,
            'due_date'        => now()->addDays(10),
        ]);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame(Invoice::STATUS_SENT, $invoice->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_customer_is_notified_when_the_invoice_turns_overdue(): void
    {
        $invoice = $this->pastDueInvoice();

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        Notification::assertSentTo(
            $invoice->customer,
            InvoiceOverdueNotification::class
        );
    }

    /**
     * The command is scheduled hourly, so a second run must not re-notify an
     * invoice that is already overdue.
     */
    public function test_customer_is_not_notified_again_on_a_later_run(): void
    {
        $this->pastDueInvoice();

        $this->artisan('invoices:mark-overdue')->assertSuccessful();
        Notification::assertCount(1);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();
        Notification::assertCount(1);
    }

    public function test_invoice_without_a_customer_email_still_becomes_overdue(): void
    {
        $customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'email'           => null,
        ]);

        $invoice = Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id'     => $customer->id,
            'status'          => Invoice::STATUS_SENT,
            'due_date'        => now()->subDays(3),
        ]);

        $this->artisan('invoices:mark-overdue')->assertSuccessful();

        $this->assertSame(Invoice::STATUS_OVERDUE, $invoice->fresh()->status);
        Notification::assertNothingSent();
    }
}
