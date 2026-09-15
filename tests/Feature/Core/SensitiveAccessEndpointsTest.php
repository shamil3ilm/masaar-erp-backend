<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Organization;
use App\Models\Core\SensitiveAccessLog;
use App\Models\HR\Employee;
use App\Models\Sales\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins step-up access to sensitive fields: the caller re-enters their password
 * for a token bound to them and one record, spends it once to reveal that
 * record's fields, and both steps are logged. The sensitive access log names
 * the record by its id.
 */
class SensitiveAccessEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const PASSWORD = 'correct-horse-1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->user->forceFill(['password' => self::PASSWORD])->save();
    }

    public function test_a_wrong_password_is_refused_and_five_failures_lock_the_caller_out(): void
    {
        $contact = Contact::factory()->create(['organization_id' => $this->organization->id]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->requestAccess('contact', $contact->uuid, 'wrong-password')
                ->assertStatus(401)
                ->assertJsonPath('error.message', 'Invalid password.');
        }

        $this->requestAccess('contact', $contact->uuid)
            ->assertStatus(429)
            ->assertJsonPath('error.message', 'Too many failed attempts. Try again in 15 minutes.');
    }

    public function test_a_token_reveals_a_contacts_tax_number_once_and_both_steps_are_logged(): void
    {
        $contact = Contact::factory()->create(['organization_id' => $this->organization->id, 'tax_number' => '300000000000003']);

        $token = $this->requestAccess('contact', $contact->uuid)
            ->assertOk()
            ->assertJsonPath('message', 'Access token issued.')
            ->assertJsonPath('data.expires_in', 900)
            ->json('data.access_token');

        $this->reveal('contact', $contact->uuid, $token)
            ->assertOk()
            ->assertJsonPath('message', 'Sensitive data retrieved.')
            ->assertJsonPath('data.fields', ['tax_number' => '300000000000003']);

        $this->reveal('contact', $contact->uuid, $token)
            ->assertStatus(401)
            ->assertJsonPath('error.message', 'Invalid or expired access token.');

        $this->assertSame(
            ['sensitive_data_access_granted', 'sensitive_data_revealed'],
            DB::table('activity_logs')->where('entity_id', $contact->uuid)->orderBy('id')->pluck('action')->all()
        );

        $log = SensitiveAccessLog::withoutGlobalScopes()->sole();
        $this->assertSame('contact', $log->model_type);
        $this->assertSame($contact->id, (int) $log->model_id);
        $this->assertSame('tax_number', $log->sensitive_fields);
    }

    public function test_a_token_reveals_an_employees_identity_and_bank_fields(): void
    {
        $employee = Employee::factory()->create([
            'organization_id' => $this->organization->id,
            'national_id' => '1000000001',
            'bank_iban' => 'SA0380000000608010167519',
        ]);

        $token = $this->requestAccess('employee', $employee->uuid)->assertOk()->json('data.access_token');

        $this->reveal('employee', $employee->uuid, $token)
            ->assertOk()
            ->assertJsonPath('data.fields.national_id', '1000000001')
            ->assertJsonPath('data.fields.bank_iban', 'SA0380000000608010167519')
            ->assertJsonStructure(['data' => ['fields' => ['passport_number', 'bank_account_number']]]);
    }

    public function test_a_token_is_bound_to_its_record_and_the_record_to_the_organization(): void
    {
        $contact = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $other = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = Contact::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->getJson("/api/v1/sensitive/contact/{$contact->uuid}/reveal", $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Access token is required.');

        $token = $this->requestAccess('contact', $contact->uuid)->json('data.access_token');
        $this->reveal('contact', $other->uuid, $token)->assertStatus(401);

        $foreignToken = $this->requestAccess('contact', $foreign->uuid)->json('data.access_token');
        $this->reveal('contact', $foreign->uuid, $foreignToken)
            ->assertNotFound()
            ->assertJsonPath('error.message', 'Resource not found.');
    }

    private function requestAccess(string $type, string $id, string $password = self::PASSWORD): \Illuminate\Testing\TestResponse
    {
        return $this->apiPost('/sensitive/request-access', [
            'password' => $password,
            'resource_type' => $type,
            'resource_id' => $id,
        ]);
    }

    private function reveal(string $type, string $id, string $token): \Illuminate\Testing\TestResponse
    {
        return $this->getJson("/api/v1/sensitive/{$type}/{$id}/reveal", $this->authHeaders(['X-Sensitive-Token' => $token]));
    }
}
