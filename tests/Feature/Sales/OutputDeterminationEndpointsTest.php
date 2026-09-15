<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\CustomerGroup;
use App\Models\Sales\OutputMessage;
use App\Models\Sales\OutputType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the output determination endpoints: output types with their condition
 * records, the message list and retrying a failed message. Output messages
 * and condition records carry no organization column; they are reached only
 * through an output type of the caller's organization.
 */
class OutputDeterminationEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.output-types.view',
            'sales.output-types.create',
            'sales.output-types.edit',
            'sales.output-types.delete',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_output_types_are_listed_latest_first_and_filtered(): void
    {
        $invoice = $this->type(['code' => 'INV', 'document_type' => 'invoice', 'created_at' => now()->subDay()]);
        $order = $this->type(['code' => 'SO', 'document_type' => 'sales_order', 'is_active' => false, 'created_at' => now()]);
        $this->type(['organization_id' => $this->otherOrg->id, 'code' => 'X']);

        $ids = fn (string $query) => array_column($this->apiGet('/sales/output-determination/output-types'.$query)->assertOk()->json('data'), 'id');

        $this->assertSame([$order->id, $invoice->id], $ids(''));
        $this->assertSame([$invoice->id], $ids('?document_type=invoice'));
        $this->assertSame([$invoice->id], $ids('?active_only=1'));
    }

    public function test_a_type_is_created_with_condition_records_shown_updated_and_deleted(): void
    {
        $group = CustomerGroup::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Contact::factory()->create(['organization_id' => $this->organization->id]);

        $created = $this->apiPost('/sales/output-determination/output-types', $this->payload([
            'condition_records' => [
                ['key_combination' => 'customer', 'customer_id' => $customer->id],
                ['key_combination' => 'customer_group', 'customer_group_id' => $group->id],
            ],
        ]));

        $created->assertStatus(201)
            ->assertJsonPath('message', 'Output type created.')
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.condition_records.0.customer_id', $customer->id)
            ->assertJsonPath('data.condition_records.1.is_active', true);
        $id = $created->json('data.id');

        $this->apiGet("/sales/output-determination/output-types/{$id}")
            ->assertOk()
            ->assertJsonPath('data.condition_records.1.customer_group_id', $group->id);

        $this->apiPut("/sales/output-determination/output-types/{$id}", ['name' => 'Invoice PDF'])
            ->assertOk()
            ->assertJsonPath('message', 'Output type updated.')
            ->assertJsonPath('data.name', 'Invoice PDF');

        $this->apiDelete("/sales/output-determination/output-types/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Output type deleted.');
        $this->assertNull(OutputType::find($id));
    }

    public function test_condition_records_refuse_another_organizations_customer_and_group(): void
    {
        $response = $this->apiPost('/sales/output-determination/output-types', $this->payload([
            'condition_records' => [[
                'key_combination' => 'customer',
                'customer_id' => Contact::factory()->create(['organization_id' => $this->otherOrg->id])->id,
                'customer_group_id' => CustomerGroup::factory()->create(['organization_id' => $this->otherOrg->id])->id,
            ]],
        ]));

        $response->assertStatus(422);
        $this->assertArrayHasKey('condition_records.0.customer_id', $response->json('errors') ?? []);
        $this->assertArrayHasKey('condition_records.0.customer_group_id', $response->json('errors') ?? []);
        $this->assertSame(0, OutputType::count());
    }

    public function test_another_organizations_type_is_not_found(): void
    {
        $foreign = $this->type(['organization_id' => $this->otherOrg->id, 'code' => 'X']);

        $this->apiGet("/sales/output-determination/output-types/{$foreign->id}")->assertNotFound();
        $this->apiPut("/sales/output-determination/output-types/{$foreign->id}", ['name' => 'x'])->assertNotFound();
        $this->apiDelete("/sales/output-determination/output-types/{$foreign->id}")->assertNotFound();
    }

    public function test_messages_list_only_the_organizations_messages_and_filter(): void
    {
        $type = $this->type();
        $failed = $this->message($type, ['status' => OutputMessage::STATUS_FAILED, 'created_at' => now()]);
        $sent = $this->message($type, ['status' => OutputMessage::STATUS_SENT, 'created_at' => now()->subDay()]);
        $this->message($this->type(['organization_id' => $this->otherOrg->id, 'code' => 'X']), ['recipient' => 'someone@example.com']);

        $ids = fn (string $query) => array_column($this->apiGet('/sales/output-determination/messages'.$query)->assertOk()->json('data'), 'id');

        $this->assertSame([$failed->id, $sent->id], $ids(''));
        $this->assertSame([$sent->id], $ids('?status=sent'));
        $this->assertSame($type->id, $this->apiGet('/sales/output-determination/messages')->json('data.0.output_type.id'));
    }

    public function test_a_failed_message_is_retried_once_and_another_organizations_is_not_found(): void
    {
        $type = $this->type(['output_medium' => 'print']);
        $failed = $this->message($type, ['status' => OutputMessage::STATUS_FAILED, 'medium' => 'print']);

        $this->apiPost("/sales/output-determination/messages/{$failed->id}/retry")
            ->assertOk()
            ->assertJsonPath('message', 'Output message dispatched.')
            ->assertJsonPath('data.status', OutputMessage::STATUS_SENT)
            ->assertJsonPath('data.retry_count', 1);

        $this->apiPost("/sales/output-determination/messages/{$failed->id}/retry")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATUS');

        $foreign = $this->message(
            $this->type(['organization_id' => $this->otherOrg->id, 'code' => 'X']),
            ['status' => OutputMessage::STATUS_FAILED, 'medium' => 'print']
        );
        $this->apiPost("/sales/output-determination/messages/{$foreign->id}/retry")->assertNotFound();
        $this->assertSame(OutputMessage::STATUS_FAILED, $foreign->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'INV1',
            'name' => 'Invoice email',
            'document_type' => 'invoice',
            'output_medium' => 'email',
            'dispatch_time' => 'on_post',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function type(array $attributes = []): OutputType
    {
        $type = new OutputType();
        $type->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'code' => 'T1',
            'name' => 'Type',
            'document_type' => 'invoice',
            'output_medium' => 'email',
            'dispatch_time' => 'on_post',
            'is_active' => true,
        ], $attributes))->save();

        return $type;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function message(OutputType $type, array $attributes = []): OutputMessage
    {
        $message = new OutputMessage();
        $message->forceFill(array_merge([
            'output_type_id' => $type->id,
            'document_type' => 'invoice',
            'document_id' => 1,
            'status' => OutputMessage::STATUS_PENDING,
            'medium' => 'email',
            'retry_count' => 0,
        ], $attributes))->save();

        return $message;
    }
}
