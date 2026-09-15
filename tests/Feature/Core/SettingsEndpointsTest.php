<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Branch;
use App\Models\Core\NumberSequence;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the organization settings, number sequence, cache and regional default
 * endpoints. A bulk or group update applies all of its values or none, and a
 * sequence names a branch of the caller's organization.
 */
class SettingsEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.settings.view', 'core.settings.edit']);
    }

    public function test_a_setting_is_read_updated_refused_and_reset(): void
    {
        $this->assertArrayHasKey('org.date_format', $this->apiGet('/settings')->assertOk()->json('data'));

        $this->apiPut('/settings/org.date_format', ['value' => 'd/m/Y'])
            ->assertOk()
            ->assertJsonPath('message', 'Setting updated successfully')
            ->assertJsonPath('data.value', 'd/m/Y');

        $this->apiPut('/settings/org.date_format', ['value' => 'nonsense'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('value');

        $this->apiDelete('/settings/org.date_format')
            ->assertOk()
            ->assertJsonPath('message', 'Setting reset to default')
            ->assertJsonPath('data.value', 'Y-m-d');
    }

    public function test_a_bulk_update_with_an_invalid_value_saves_nothing(): void
    {
        // Regional defaults may already have set the format, so compare with what is stored now.
        $before = $this->apiGet('/settings/org.date_format')->assertOk()->json('data.value');
        $changed = $before === 'm/d/Y' ? 'd-m-Y' : 'm/d/Y';

        $this->apiPut('/settings/bulk', ['settings' => [
            'org.date_format' => $changed,
            'org.first_day_of_week' => 9,
        ]])->assertStatus(422);

        $this->apiGet('/settings/org.date_format')->assertOk()->assertJsonPath('data.value', $before);

        $this->apiPut('/settings/group/org', ['settings' => [
            'date_format' => $changed,
            'first_day_of_week' => 9,
        ]])->assertStatus(422);

        $this->apiGet('/settings/org.date_format')->assertOk()->assertJsonPath('data.value', $before);

        $this->apiPut('/settings/bulk', ['settings' => ['org.date_format' => $changed, 'org.first_day_of_week' => 1]])
            ->assertOk()
            ->assertJsonPath('message', 'Settings updated successfully');
        $this->apiGet('/settings/org.date_format')->assertOk()->assertJsonPath('data.value', $changed);
        $this->apiGet('/settings/org.first_day_of_week')->assertOk()->assertJsonPath('data.value', 1);
    }

    public function test_a_number_sequence_is_configured_listed_shown_and_previewed(): void
    {
        $this->apiGet('/sequences/invoice')
            ->assertOk()
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.current_number', 0);

        $updated = $this->apiPut('/sequences/invoice', [
            'prefix' => 'TST-',
            'padding' => 4,
            'include_year' => false,
            'current_number' => 41,
        ]);
        $updated->assertOk()->assertJsonPath('message', 'Number sequence updated')->assertJsonPath('data.type', 'invoice');
        $next = $updated->json('data.next_number');
        $this->assertStringContainsString('TST-', $next);
        $this->assertStringContainsString('0042', $next);

        $this->apiGet('/sequences')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'invoice')
            ->assertJsonPath('data.0.next_number', $next);

        $this->apiGet('/sequences/invoice')
            ->assertOk()
            ->assertJsonPath('data.is_default', false)
            ->assertJsonPath('data.current_number', 41)
            ->assertJsonPath('data.next_number', $next);

        $this->apiGet('/sequences/invoice/preview')->assertOk()->assertJsonPath('data.next_number', $next);
        $this->assertSame(1, NumberSequence::where('type', 'invoice')->count());
    }

    public function test_another_organizations_branch_is_refused_for_a_sequence(): void
    {
        $foreignBranch = Branch::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPut('/sequences/invoice', ['branch_id' => $foreignBranch->id, 'prefix' => 'X-'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('branch_id');
        $this->assertSame(0, NumberSequence::count());
    }

    public function test_cache_is_cleared_and_regional_defaults_are_applied(): void
    {
        $this->apiPost('/settings/cache/clear')->assertOk()->assertJsonPath('message', 'Settings cache cleared');

        $this->apiGet('/settings/regions')->assertOk()->assertJsonPath('message', 'Supported regions retrieved.');
        $this->apiGet('/settings/regions/sa/preview')->assertOk()->assertJsonPath('data.country_code', 'SA');

        $this->apiPost('/settings/bulk-reset-to-region')
            ->assertOk()
            ->assertJsonPath('message', 'All settings reset to regional defaults.')
            ->assertJsonPath('data.country_code', 'SA');

        $this->organization->forceFill(['country_code' => ''])->save();
        $this->apiPost('/settings/bulk-reset-to-region')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MISSING_COUNTRY_CODE');
    }
}
