<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\PaymentFile;
use App\Models\Accounting\PaymentRun;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins what the payment file endpoints return for files that exist: listing
 * filters, the detail and download payloads, the submit and acknowledge
 * transitions, and the 404 for another organization's file or run.
 */
class PaymentFileBehaviourTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser([
            'accounting.payment-files.manage',
            'accounting.payment-files.view',
        ]);
    }

    private function makeRun(?int $organizationId = null): PaymentRun
    {
        return PaymentRun::create([
            'organization_id'   => $organizationId ?? $this->organization->id,
            'run_reference'     => 'RUN-' . fake()->unique()->numerify('######'),
            'payment_direction' => 'outgoing',
            'payment_date'      => '2025-01-31',
            'status'            => PaymentRun::STATUS_DRAFT,
            'created_by'        => $this->user->id,
        ]);
    }

    private function makeFile(array $overrides = []): PaymentFile
    {
        $organizationId = $overrides['organization_id'] ?? $this->organization->id;

        return PaymentFile::create(array_merge([
            'organization_id'        => $organizationId,
            'payment_run_id'         => $this->makeRun($organizationId)->id,
            'file_format'            => PaymentFile::FORMAT_SEPA_CT,
            'file_name'              => 'payments.xml',
            'file_content'           => '<Document/>',
            'message_id'             => 'MSG-' . fake()->unique()->numerify('######'),
            'creation_datetime'      => now(),
            'number_of_transactions' => 1,
            'total_amount'           => 100,
            'currency_code'          => 'SAR',
            'status'                 => PaymentFile::STATUS_GENERATED,
            'created_by'             => $this->user->id,
        ], $overrides));
    }

    public function test_index_applies_non_empty_filters_with_the_run(): void
    {
        $sepa = $this->makeFile();
        $ach = $this->makeFile(['file_format' => PaymentFile::FORMAT_ACH, 'status' => PaymentFile::STATUS_SUBMITTED]);

        $ids = fn (string $query): array => array_column(
            $this->withToken($this->token)->getJson("/api/v1/payment-files?{$query}")->json('data'),
            'id'
        );

        $this->assertEqualsCanonicalizing([$sepa->id, $ach->id], $ids(''));
        $this->assertSame([$ach->id], $ids('status=' . PaymentFile::STATUS_SUBMITTED));
        $this->assertSame([$sepa->id], $ids('file_format=' . PaymentFile::FORMAT_SEPA_CT));
        $this->assertEqualsCanonicalizing([$sepa->id, $ach->id], $ids('status='));

        $this->withToken($this->token)->getJson('/api/v1/payment-files?per_page=1')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data' => [['payment_run' => ['id', 'uuid']]]]);
    }

    public function test_show_returns_the_file_with_its_run(): void
    {
        $file = $this->makeFile();

        $this->withToken($this->token)->getJson('/api/v1/payment-files/' . $file->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $file->id)
            ->assertJsonPath('data.payment_run.id', $file->payment_run_id)
            ->assertJsonPath('data.file_content', '<Document/>');
    }

    public function test_download_returns_the_file_content(): void
    {
        $file = $this->makeFile();

        $this->withToken($this->token)->getJson('/api/v1/payment-files/' . $file->id . '/download')
            ->assertStatus(200)
            ->assertJsonPath('data.file_name', 'payments.xml')
            ->assertJsonPath('data.file_format', PaymentFile::FORMAT_SEPA_CT)
            ->assertJsonPath('data.file_content', '<Document/>')
            ->assertJsonPath('data.content_type', 'application/xml');
    }

    public function test_submit_then_acknowledge_moves_the_file_through_its_statuses(): void
    {
        $file = $this->makeFile();

        $this->withToken($this->token)->postJson('/api/v1/payment-files/' . $file->id . '/submit')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Payment file marked as submitted.')
            ->assertJsonPath('data.status', PaymentFile::STATUS_SUBMITTED);

        $this->withToken($this->token)->postJson('/api/v1/payment-files/' . $file->id . '/acknowledge')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Payment file acknowledged.')
            ->assertJsonPath('data.status', PaymentFile::STATUS_ACKNOWLEDGED);
    }

    public function test_submitting_a_file_that_is_not_generated_is_refused(): void
    {
        $file = $this->makeFile(['status' => PaymentFile::STATUS_ACKNOWLEDGED]);

        $this->withToken($this->token)->postJson('/api/v1/payment-files/' . $file->id . '/submit')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_ARGUMENT')
            ->assertJsonPath('error.message', 'Only generated files can be marked as submitted.');

        $this->assertSame(PaymentFile::STATUS_ACKNOWLEDGED, $file->fresh()->status);
    }

    public function test_acknowledging_a_file_that_is_not_submitted_is_refused(): void
    {
        $file = $this->makeFile();

        $this->withToken($this->token)->postJson('/api/v1/payment-files/' . $file->id . '/acknowledge')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_ARGUMENT')
            ->assertJsonPath('error.message', 'Only submitted files can be acknowledged.');

        $this->assertSame(PaymentFile::STATUS_GENERATED, $file->fresh()->status);
    }

    public function test_another_organizations_file_or_run_is_not_found(): void
    {
        $otherOrganization = Organization::factory()->create();
        $file = $this->makeFile(['organization_id' => $otherOrganization->id]);

        foreach (['', '/download'] as $suffix) {
            $this->withToken($this->token)->getJson('/api/v1/payment-files/' . $file->id . $suffix)->assertStatus(404);
        }

        foreach (['/submit', '/acknowledge'] as $suffix) {
            $this->withToken($this->token)->postJson('/api/v1/payment-files/' . $file->id . $suffix)->assertStatus(404);
        }

        $this->withToken($this->token)->postJson('/api/v1/payment-files/generate', [
            'payment_run_id' => $file->payment_run_id,
            'file_format'    => PaymentFile::FORMAT_SEPA_CT,
        ])->assertStatus(404);

        $this->assertSame(PaymentFile::STATUS_GENERATED, $file->fresh()->status);
    }
}
