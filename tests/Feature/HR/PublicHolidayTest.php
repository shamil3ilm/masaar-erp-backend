<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\HR\Leave\PublicHoliday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Public holidays: listed by date and filtered by year, branch and whether
 * they are mandatory, created singly or in bulk, inside the caller's
 * organization only.
 */
class PublicHolidayTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private string $baseUrl = '/hr/leave-management/public-holidays';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['hr.leave.view', 'hr.leave.manage']);
    }

    public function test_holidays_are_listed_by_date_as_a_list_or_a_page(): void
    {
        $december = $this->holiday(['name' => 'National Day', 'holiday_date' => '2026-12-02']);
        $january = $this->holiday(['name' => 'New Year', 'holiday_date' => '2026-01-01']);
        $this->holiday(['name' => 'Theirs', 'holiday_date' => '2026-02-01'], Organization::factory()->create());

        $list = $this->apiGet($this->baseUrl);
        $list->assertOk();
        $this->assertSame([$january->id, $december->id], array_column($list->json('data'), 'id'));

        $page = $this->apiGet("{$this->baseUrl}?per_page=1");
        $this->assertPaginatedResponse($page);
        $this->assertSame([$january->id], array_column($page->json('data'), 'id'));
    }

    public function test_holidays_are_filtered_by_year_branch_and_whether_they_are_mandatory(): void
    {
        $otherBranch = Branch::factory()->create(['organization_id' => $this->organization->id]);

        $organizationWide = $this->holiday(['holiday_date' => '2026-01-01', 'branch_id' => null]);
        $thisBranch = $this->holiday(['holiday_date' => '2026-02-01', 'branch_id' => $this->branch->id]);
        $this->holiday(['holiday_date' => '2026-03-01', 'branch_id' => $otherBranch->id]);
        $this->holiday(['holiday_date' => '2026-04-01', 'branch_id' => $this->branch->id, 'is_optional' => true]);
        $this->holiday(['holiday_date' => '2025-02-01', 'branch_id' => $this->branch->id, 'year' => 2025]);

        $response = $this->apiGet("{$this->baseUrl}?year=2026&branch_id={$this->branch->id}&mandatory_only=1");

        $response->assertOk();
        $this->assertSame([$organizationWide->id, $thisBranch->id], array_column($response->json('data'), 'id'));
    }

    public function test_a_holiday_is_shown_with_its_branch(): void
    {
        $holiday = $this->holiday(['branch_id' => $this->branch->id]);

        $this->apiGet("{$this->baseUrl}/{$holiday->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $holiday->id)
            ->assertJsonPath('data.branch.id', $this->branch->id);
    }

    public function test_a_holiday_cannot_name_another_organizations_branch(): void
    {
        $theirBranch = Branch::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPost($this->baseUrl, [
            'name' => 'Founding Day',
            'holiday_date' => '2026-02-22',
            'year' => 2026,
            'branch_id' => $theirBranch->id,
        ])->assertStatus(422);

        $this->apiPost("{$this->baseUrl}/bulk", ['holidays' => [[
            'name' => 'Founding Day',
            'holiday_date' => '2026-02-22',
            'year' => 2026,
            'branch_id' => $theirBranch->id,
        ]]])->assertStatus(422);

        $this->assertSame(0, PublicHoliday::withoutGlobalScopes()->count());
    }

    public function test_holidays_are_created_one_at_a_time_or_in_bulk(): void
    {
        $this->apiPost($this->baseUrl, ['name' => 'Founding Day', 'holiday_date' => '2026-02-22', 'year' => 2026])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Founding Day')
            ->assertJsonPath('data.organization_id', $this->organization->id);

        $this->apiPost("{$this->baseUrl}/bulk", ['holidays' => [
            ['name' => 'Eid al-Fitr', 'holiday_date' => '2026-03-20', 'year' => 2026],
            ['name' => 'Eid al-Adha', 'holiday_date' => '2026-05-27', 'year' => 2026, 'branch_id' => $this->branch->id],
        ]])
            ->assertStatus(201)
            ->assertJsonCount(2, 'data');

        $this->assertSame(3, PublicHoliday::count());
    }

    public function test_a_holiday_is_updated_and_deleted(): void
    {
        $holiday = $this->holiday();

        $this->apiPut("{$this->baseUrl}/{$holiday->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->apiDelete("{$this->baseUrl}/{$holiday->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Public holiday deleted successfully.');

        $this->assertDatabaseMissing('public_holidays', ['id' => $holiday->id]);
    }

    private function holiday(array $overrides = [], ?Organization $organization = null): PublicHoliday
    {
        return PublicHoliday::create(array_merge([
            'organization_id' => ($organization ?? $this->organization)->id,
            'name' => 'Holiday',
            'holiday_date' => '2026-06-01',
            'is_optional' => false,
            'year' => 2026,
        ], $overrides));
    }
}
