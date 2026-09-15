<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\EdiMessage;
use App\Models\Core\EdiPartner;
use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the EDI endpoints: trading partners, receiving, processing and sending
 * messages and a partner's history. A partner names a contact of the caller's
 * organization, a message a partner of it, and an inbound document's lines
 * only products of it.
 */
class EdiEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.edi.view', 'core.edi.manage']);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_a_partner_is_created_shown_updated_listed_and_deleted(): void
    {
        $contact = Contact::factory()->create(['organization_id' => $this->organization->id]);

        $id = $this->apiPost('/edi/partners', $this->partnerPayload(['contact_id' => $contact->id]))
            ->assertStatus(201)
            ->assertJsonPath('message', 'EDI partner created successfully.')
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->json('data.id');

        $this->apiGet("/edi/partners/{$id}")->assertOk()->assertJsonPath('data.partner_code', 'ACME');

        $this->apiPut("/edi/partners/{$id}", ['partner_name' => 'Acme Trading'])
            ->assertOk()
            ->assertJsonPath('message', 'EDI partner updated successfully.')
            ->assertJsonPath('data.partner_name', 'Acme Trading');

        $this->apiGet('/edi/partners?search=Acme')->assertOk()->assertJsonPath('meta.total', 1);
        $this->apiGet("/edi/partners/{$id}/history")->assertOk()->assertJsonPath('meta.total', 0);

        $this->apiDelete("/edi/partners/{$id}")->assertOk()->assertJsonPath('message', 'EDI partner deleted successfully.');
        $this->assertSoftDeleted('edi_partners', ['id' => $id]);
    }

    public function test_another_organizations_contact_and_partner_are_refused(): void
    {
        $foreignContact = Contact::factory()->create(['organization_id' => $this->otherOrg->id]);
        $foreignPartner = $this->partner($this->otherOrg->id);

        $this->apiPost('/edi/partners', $this->partnerPayload(['contact_id' => $foreignContact->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('contact_id');

        $this->apiPost('/edi/messages/receive', [
            'partner_id' => $foreignPartner->id,
            'message_type' => 'ORDERS',
            'raw_content' => '{"lines": []}',
        ])->assertStatus(422)->assertJsonValidationErrors('partner_id');

        $this->apiPost('/edi/messages/send', [
            'partner_id' => $foreignPartner->id,
            'message_type' => 'ORDERS',
            'data' => ['id' => 1],
        ])->assertStatus(422)->assertJsonValidationErrors('partner_id');

        $this->assertSame(0, EdiPartner::count());
        $this->assertSame(0, EdiMessage::count());
    }

    public function test_another_organizations_partner_and_message_are_not_found(): void
    {
        $foreignPartner = $this->partner($this->otherOrg->id);
        $foreignMessage = EdiMessage::withoutGlobalScopes()->create([
            'organization_id' => $this->otherOrg->id,
            'edi_partner_id' => $foreignPartner->id,
            'message_type' => 'ORDERS',
            'direction' => EdiMessage::DIRECTION_INBOUND,
            'status' => EdiMessage::STATUS_RECEIVED,
            'raw_content' => '{}',
        ]);

        $this->apiGet("/edi/partners/{$foreignPartner->id}")->assertNotFound();
        $this->apiPut("/edi/partners/{$foreignPartner->id}", ['partner_name' => 'X'])->assertNotFound();
        $this->apiDelete("/edi/partners/{$foreignPartner->id}")->assertNotFound();
        $this->apiGet("/edi/partners/{$foreignPartner->id}/history")->assertNotFound();
        $this->apiGet("/edi/messages/{$foreignMessage->id}")->assertNotFound();
        $this->apiPost("/edi/messages/{$foreignMessage->id}/process")->assertNotFound();
        $this->apiPost("/edi/messages/{$foreignMessage->id}/reprocess")->assertNotFound();
    }

    public function test_an_inbound_message_is_received_listed_and_shown(): void
    {
        $partner = $this->partner($this->organization->id);

        $id = $this->apiPost('/edi/messages/receive', [
            'partner_id' => $partner->id,
            'message_type' => 'DESADV',
            'raw_content' => "UNH+1+DESADV'BGM+351+123'",
        ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'EDI message received successfully.')
            ->assertJsonPath('data.status', EdiMessage::STATUS_RECEIVED)
            ->json('data.id');

        $this->apiGet('/edi/messages?direction=inbound')->assertOk()->assertJsonPath('meta.total', 1);
        $this->apiGet("/edi/messages/{$id}")
            ->assertOk()
            ->assertJsonPath('data.partner.id', $partner->id)
            ->assertJsonCount(2, 'data.segments');

        $this->apiPost("/edi/messages/{$id}/process")
            ->assertOk()
            ->assertJsonPath('message', 'EDI message processed successfully.')
            ->assertJsonPath('data.status', EdiMessage::STATUS_PROCESSED);
    }

    public function test_an_inbound_order_refuses_another_organizations_product(): void
    {
        $partner = $this->partner($this->organization->id);
        $supplier = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $foreignProduct = Product::factory()->create(['organization_id' => $this->otherOrg->id]);

        $message = EdiMessage::create([
            'organization_id' => $this->organization->id,
            'edi_partner_id' => $partner->id,
            'message_type' => 'ORDERS',
            'direction' => EdiMessage::DIRECTION_INBOUND,
            'status' => EdiMessage::STATUS_RECEIVED,
            'raw_content' => '{}',
            'parsed_content' => [
                'supplier_id' => $supplier->id,
                'lines' => [['product_id' => $foreignProduct->id, 'quantity' => 1, 'unit_price' => 10]],
            ],
        ]);

        $this->apiPost("/edi/messages/{$message->id}/process");

        $this->assertSame(0, PurchaseOrder::count());
        $this->assertSame(EdiMessage::STATUS_FAILED, $message->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function partnerPayload(array $overrides = []): array
    {
        return array_merge([
            'partner_code' => 'ACME',
            'partner_name' => 'Acme',
            'partner_type' => 'vendor',
            'edi_standard' => 'edifact',
        ], $overrides);
    }

    private function partner(int $organizationId): EdiPartner
    {
        return EdiPartner::withoutGlobalScopes()->create([
            'organization_id' => $organizationId,
            'partner_code' => 'P'.$organizationId,
            'partner_name' => 'Partner '.$organizationId,
            'partner_type' => 'vendor',
            'edi_standard' => 'edifact',
            'is_active' => true,
        ]);
    }
}
