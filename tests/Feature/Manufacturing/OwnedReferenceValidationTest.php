<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Core\Organization;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\CapaEightD;
use App\Models\Manufacturing\CertificateOfAnalysis;
use App\Models\Manufacturing\DynamicModificationRule;
use App\Models\Manufacturing\RoutingHeader;
use App\Models\Manufacturing\WorkCenter;
use App\Models\Sales\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Manufacturing endpoints refuse an id that belongs to another organization.
 *
 * Each case sends the same request twice: once with a row of a second
 * organization, which the field must reject, and once with the caller's own
 * row, which the field must accept. The second call only asserts that the
 * field itself passed validation, so a case stays about the rule rather than
 * about whatever the endpoint does afterwards.
 */
class OwnedReferenceValidationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.quality.view',
            'manufacturing.quality.create',
            'manufacturing.quality.edit',
            'manufacturing.quality.delete',
            'manufacturing.quality.manage',
            'manufacturing.planning.view',
            'manufacturing.planning.manage',
        ]);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_a_certificate_is_not_issued_for_another_organizations_product_or_contact(): void
    {
        $url = '/api/v1/manufacturing/quality/certificates-of-analysis';

        $this->postJson($url, $this->certificatePayload(
            $this->product($this->otherOrg)->id,
            $this->contact($this->otherOrg)->id,
        ), $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'contact_id']);

        $this->postJson($url, $this->certificatePayload(
            $this->product()->id,
            $this->contact()->id,
        ), $this->authHeaders())
            ->assertJsonMissingValidationErrors(['product_id', 'contact_id']);
    }

    public function test_a_certificate_is_not_updated_or_issued_to_another_organizations_contact(): void
    {
        $certificate = CertificateOfAnalysis::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product()->id,
            'issued_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $url = "/api/v1/manufacturing/quality/certificates-of-analysis/{$certificate->uuid}";

        $this->putJson($url, ['contact_id' => $this->contact($this->otherOrg)->id], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_id');

        $this->putJson($url, ['contact_id' => $this->contact()->id], $this->authHeaders())
            ->assertJsonMissingValidationErrors('contact_id');

        $this->postJson("{$url}/issue", ['contact_id' => $this->contact($this->otherOrg)->id], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_id');
    }

    public function test_a_routing_is_not_created_for_another_organizations_product_or_work_center(): void
    {
        $url = '/api/v1/manufacturing/routings';

        $this->postJson($url, $this->routingPayload(
            $this->product($this->otherOrg)->id,
            $this->workCenter($this->otherOrg)->id,
        ), $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'operations.0.work_center_id']);

        $this->postJson($url, $this->routingPayload(
            $this->product()->id,
            $this->workCenter()->id,
        ), $this->authHeaders())
            ->assertJsonMissingValidationErrors(['product_id', 'operations.0.work_center_id']);
    }

    public function test_an_operation_is_not_added_on_another_organizations_work_center(): void
    {
        $routing = RoutingHeader::factory()->create([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product()->id,
        ]);

        $url = "/api/v1/manufacturing/routings/{$routing->id}/operations";

        $this->postJson($url, $this->operationPayload($this->workCenter($this->otherOrg)->id), $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('work_center_id');

        $this->postJson($url, $this->operationPayload($this->workCenter()->id), $this->authHeaders())
            ->assertJsonMissingValidationErrors('work_center_id');
    }

    public function test_an_inspection_result_is_not_recorded_for_another_organizations_product(): void
    {
        $rule = DynamicModificationRule::factory()->create(['organization_id' => $this->organization->id]);

        $url = "/api/v1/manufacturing/dynamic-modification-rules/{$rule->uuid}/evaluate";

        $this->postJson($url, ['product_id' => $this->product($this->otherOrg)->id, 'passed' => true], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $this->postJson($url, ['product_id' => $this->product()->id, 'passed' => true], $this->authHeaders())
            ->assertJsonMissingValidationErrors('product_id');
    }

    public function test_another_organizations_user_does_not_champion_an_eight_d_team(): void
    {
        $record = CapaEightD::factory()->create(['organization_id' => $this->organization->id]);

        $url = "/api/v1/manufacturing/capa-8d/{$record->uuid}/steps/d1";

        $this->postJson($url, ['d1_champion_id' => $this->userOf($this->otherOrg)->id], $this->authHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('d1_champion_id');

        $this->postJson($url, ['d1_champion_id' => $this->user->id], $this->authHeaders())
            ->assertJsonMissingValidationErrors('d1_champion_id');
    }

    /** @return array<string, mixed> */
    private function certificatePayload(int $productId, int $contactId): array
    {
        return [
            'product_id' => $productId,
            'contact_id' => $contactId,
            'test_results' => [
                ['parameter' => 'Purity', 'result' => '99.5%', 'pass_fail' => 'pass'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function routingPayload(int $productId, int $workCenterId): array
    {
        return [
            'product_id' => $productId,
            'operations' => [$this->operationPayload($workCenterId)],
        ];
    }

    /** @return array<string, mixed> */
    private function operationPayload(int $workCenterId): array
    {
        return [
            'operation_code' => 'OP10',
            'description' => 'Assemble',
            'work_center_id' => $workCenterId,
        ];
    }

    private function product(?Organization $organization = null): Product
    {
        return Product::factory()->create(['organization_id' => ($organization ?? $this->organization)->id]);
    }

    private function contact(?Organization $organization = null): Contact
    {
        return Contact::factory()->create(['organization_id' => ($organization ?? $this->organization)->id]);
    }

    private function workCenter(?Organization $organization = null): WorkCenter
    {
        return WorkCenter::factory()->create(['organization_id' => ($organization ?? $this->organization)->id]);
    }

    private function userOf(Organization $organization): User
    {
        return User::factory()->create(['organization_id' => $organization->id]);
    }
}
