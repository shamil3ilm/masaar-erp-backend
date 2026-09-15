<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Organization;
use App\Models\HR\Employee;
use App\Models\HR\PerDiemRate;
use App\Models\HR\TravelExpenseClaim;
use App\Models\HR\TravelExpenseReport;
use App\Models\HR\TravelExpenseType;
use App\Models\HR\TravelRequest;
use App\Services\HR\TravelExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Per diem rates, travel requests, expense claims and expense reports.
 *
 * Two controllers register routes under /hr/travel and some paths overlap;
 * these tests pin which one answers, the listings each returns and the
 * references a request, claim or report may make.
 */
class TravelExpenseEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/travel';

    private Organization $otherOrganization;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.travel.view', 'hr.travel.manage']);

        $this->otherOrganization = Organization::factory()->create();
        $this->employee = $this->employee($this->organization);
    }

    public function test_per_diem_rates_are_the_active_ones_of_a_country(): void
    {
        $riyadh = $this->rate($this->organization, 'SAU', 'Riyadh');
        $this->rate($this->organization, 'SAU', 'Jeddah', ['is_active' => false]);
        $this->rate($this->organization, 'ARE', 'Dubai');
        $this->rate($this->otherOrganization, 'SAU', 'Riyadh');

        $response = $this->apiGet("{$this->baseUrl}/per-diem-rates?country=SAU");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$riyadh->id], array_column($response->json('data'), 'id'));
    }

    public function test_travel_requests_are_filtered_by_employee_and_status(): void
    {
        $wanted = $this->travelRequest($this->employee, TravelRequest::STATUS_DRAFT);
        $this->travelRequest($this->employee, TravelRequest::STATUS_SUBMITTED);
        $this->travelRequest($this->employee($this->organization), TravelRequest::STATUS_DRAFT);

        $response = $this->apiGet("{$this->baseUrl}/requests?employee_id={$this->employee->id}&status=draft");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_travel_request_is_created_for_an_employee_of_the_organization(): void
    {
        $this->apiPost("{$this->baseUrl}/requests", $this->requestPayload())
            ->assertCreated()
            ->assertJsonPath('data.employee.id', $this->employee->id)
            ->assertJsonPath('data.status', TravelRequest::STATUS_DRAFT);
    }

    public function test_a_travel_request_cannot_name_another_organizations_employee(): void
    {
        $this->apiPost("{$this->baseUrl}/requests", $this->requestPayload(['employee_id' => $this->employee($this->otherOrganization)->id]))
            ->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->assertSame(0, TravelRequest::withoutGlobalScopes()->count());
    }

    public function test_a_travel_request_is_shown_by_uuid_with_its_employee(): void
    {
        $request = $this->travelRequest($this->employee, TravelRequest::STATUS_DRAFT);

        $this->apiGet("{$this->baseUrl}/requests/{$request->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $request->id)
            ->assertJsonPath('data.employee.id', $this->employee->id);
    }

    public function test_a_submitted_request_is_approved_by_uuid(): void
    {
        $request = $this->travelRequest($this->employee, TravelRequest::STATUS_SUBMITTED);

        $this->apiPost("{$this->baseUrl}/requests/{$request->uuid}/approve")->assertOk();

        $this->assertSame(TravelRequest::STATUS_APPROVED, $request->fresh()->status);
    }

    /**
     * A rejection made from a copy read before the request was approved must
     * not turn the approved request into a rejected one.
     */
    public function test_a_request_approved_meanwhile_is_not_rejected_from_a_stale_copy(): void
    {
        $stale = $this->travelRequest($this->employee, TravelRequest::STATUS_SUBMITTED);
        TravelRequest::whereKey($stale->id)->toBase()->update(['status' => TravelRequest::STATUS_APPROVED]);

        try {
            app(TravelExpenseService::class)->reject($stale, 'Budget');
            $this->fail('An approved request was rejected.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(TravelRequest::STATUS_APPROVED, $stale->fresh()->status);
        }
    }

    public function test_claims_are_filtered_by_employee(): void
    {
        $wanted = $this->claim($this->employee, TravelExpenseClaim::STATUS_DRAFT);
        $this->claim($this->employee($this->organization), TravelExpenseClaim::STATUS_DRAFT);

        $response = $this->apiGet("{$this->baseUrl}/claims?employee_id={$this->employee->id}");

        $this->assertPaginatedResponse($response);
        $this->assertSame([$wanted->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_claim_cannot_name_another_organizations_employee_or_request(): void
    {
        $this->apiPost("{$this->baseUrl}/claims", ['employee_id' => $this->employee($this->otherOrganization)->id])
            ->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $theirRequest = $this->travelRequest($this->employee($this->otherOrganization), TravelRequest::STATUS_APPROVED);
        $this->apiPost("{$this->baseUrl}/claims", ['employee_id' => $this->employee->id, 'travel_request_id' => $theirRequest->id])
            ->assertStatus(422)->assertJsonValidationErrors('travel_request_id');

        $this->apiPost("{$this->baseUrl}/claims", ['employee_id' => $this->employee->id])->assertCreated();
        $this->assertSame(1, TravelExpenseClaim::withoutGlobalScopes()->count());
    }

    public function test_another_organizations_claim_is_not_found_and_a_submitted_claim_is_not_deleted(): void
    {
        $theirs = $this->claim($this->employee($this->otherOrganization), TravelExpenseClaim::STATUS_DRAFT);
        $this->apiGet("{$this->baseUrl}/claims/{$theirs->id}")->assertNotFound();

        $submitted = $this->claim($this->employee, TravelExpenseClaim::STATUS_SUBMITTED);
        $this->deleteJson("/api/v1{$this->baseUrl}/claims/{$submitted->id}", [], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_STATE');

        $this->assertNotNull($submitted->fresh());
    }

    public function test_expense_types_are_listed_and_created_in_the_organization(): void
    {
        $this->expenseType($this->organization, 'HTL', 'Hotel');
        $this->expenseType($this->organization, 'OLD', 'Old', ['is_active' => false]);
        $this->expenseType($this->otherOrganization, 'THR', 'Theirs');

        $response = $this->apiGet("{$this->baseUrl}/expense-types?active_only=1");
        $this->assertSame(['Hotel'], array_column($response->json('data'), 'name'));

        $this->apiPost("{$this->baseUrl}/expense-types", ['code' => 'TAX', 'name' => 'Taxi', 'category' => 'transport'])
            ->assertCreated()
            ->assertJsonPath('data.organization_id', $this->organization->id);
    }

    public function test_an_expense_report_is_listed_under_its_request(): void
    {
        $request = $this->travelRequest($this->employee, TravelRequest::STATUS_APPROVED);
        $type = $this->expenseType($this->organization, 'HTL', 'Hotel');

        $this->apiPost("{$this->baseUrl}/requests/{$request->uuid}/reports", $this->reportPayload($type))
            ->assertCreated()
            ->assertJsonPath('data.total_amount', '450.0000');

        $response = $this->apiGet("{$this->baseUrl}/requests/{$request->uuid}/reports");

        $this->assertPaginatedResponse($response);
        $response->assertJsonCount(1, 'data')->assertJsonPath('data.0.lines.0.expense_type.id', $type->id);
    }

    public function test_an_expense_report_cannot_name_another_organizations_employee_or_expense_type(): void
    {
        $request = $this->travelRequest($this->employee, TravelRequest::STATUS_APPROVED);
        $ours = $this->expenseType($this->organization, 'HTL', 'Hotel');
        $theirs = $this->expenseType($this->otherOrganization, 'THR', 'Theirs');

        $this->apiPost("{$this->baseUrl}/requests/{$request->uuid}/reports", ['employee_id' => $this->employee($this->otherOrganization)->id] + $this->reportPayload($ours))
            ->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->apiPost("{$this->baseUrl}/requests/{$request->uuid}/reports", $this->reportPayload($theirs))
            ->assertStatus(422)->assertJsonValidationErrors('lines.0.expense_type_id');

        $this->assertSame(0, TravelExpenseReport::withoutGlobalScopes()->count());
    }

    private function requestPayload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'purpose' => 'Client visit',
            'departure_date' => '2026-03-02',
            'return_date' => '2026-03-04',
            'destination_country' => 'ARE',
            'estimated_cost' => 3000,
        ], $overrides);
    }

    private function reportPayload(TravelExpenseType $type): array
    {
        return [
            'employee_id' => $this->employee->id,
            'lines' => [[
                'expense_type_id' => $type->id,
                'expense_date' => '2026-03-02',
                'description' => 'Hotel night',
                'amount' => 450,
            ]],
        ];
    }

    private function employee(Organization $organization): Employee
    {
        return Employee::factory()->create([
            'organization_id' => $organization->id,
            'branch_id' => $organization->is($this->organization) ? $this->branch->id : null,
        ]);
    }

    private function rate(Organization $organization, string $country, string $city, array $overrides = []): PerDiemRate
    {
        return PerDiemRate::create(array_merge([
            'organization_id' => $organization->id,
            'destination_country' => $country,
            'destination_city' => $city,
            'daily_allowance' => 500,
            'currency_code' => 'SAR',
            'is_active' => true,
        ], $overrides));
    }

    private function travelRequest(Employee $employee, string $status): TravelRequest
    {
        static $number = 0;

        return TravelRequest::create([
            'organization_id' => $employee->organization_id,
            'employee_id' => $employee->id,
            'request_number' => 'TRV-TEST-'.++$number,
            'purpose' => 'Client visit',
            'departure_date' => '2026-03-02',
            'return_date' => '2026-03-04',
            'destination_country' => 'ARE',
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }

    private function claim(Employee $employee, string $status): TravelExpenseClaim
    {
        static $number = 0;

        return TravelExpenseClaim::create([
            'organization_id' => $employee->organization_id,
            'employee_id' => $employee->id,
            'claim_number' => 'TEC-TEST-'.++$number,
            'claim_date' => '2026-03-05',
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }

    private function expenseType(Organization $organization, string $code, string $name, array $overrides = []): TravelExpenseType
    {
        return TravelExpenseType::create(array_merge([
            'organization_id' => $organization->id,
            'code' => $code,
            'name' => $name,
            'category' => 'accommodation',
            'is_active' => true,
        ], $overrides));
    }
}
