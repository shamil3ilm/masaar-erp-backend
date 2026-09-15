<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Models\Automation\AutomationEmailTemplate;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the automation email template endpoints.
 */
class AutomationEmailTemplateTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['automation.email-templates.view', 'automation.email-templates.manage']);
        $this->actingAs($this->user, 'api');
    }

    public function test_index_filters_by_category_and_activity_sorted_by_name_within_the_organization(): void
    {
        $b = $this->template(['name' => 'B welcome', 'category' => 'sales']);
        $a = $this->template(['name' => 'A welcome', 'category' => 'sales']);
        $this->template(['name' => 'C welcome', 'category' => 'support']);
        $this->template(['name' => 'D welcome', 'category' => 'sales', 'is_active' => false]);
        AutomationEmailTemplate::factory()->create(['organization_id' => Organization::factory()->create()->id, 'name' => 'A other', 'category' => 'sales']);

        $response = $this->apiGet('/automation/email-templates?category=sales&is_active=true');

        $response->assertOk()->assertJsonPath('meta.per_page', 15);
        $this->assertSame([$a->id, $b->id], array_column($response->json('data'), 'id'));
    }

    public function test_store_update_preview_and_delete(): void
    {
        $created = $this->apiPost('/automation/email-templates', [
            'name' => 'Overdue reminder',
            'subject' => 'Invoice {{number}} is overdue',
            'body_html' => '<p>Hello {{name}}</p>',
            'variables' => ['number', 'name'],
        ])->assertCreated()->assertJsonPath('data.organization_id', $this->organization->id);

        $template = AutomationEmailTemplate::findOrFail($created->json('data.id'));

        $this->apiPut("/automation/email-templates/{$template->id}", ['subject' => 'Invoice {{number}} is due'])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Invoice {{number}} is due');

        $this->apiPost("/automation/email-templates/{$template->id}/preview", ['data' => ['number' => 'INV-1', 'name' => 'Sara']])
            ->assertOk()
            ->assertJsonPath('data.rendered.subject', 'Invoice INV-1 is due')
            ->assertJsonPath('data.rendered.body_html', '<p>Hello Sara</p>');

        $this->apiDelete("/automation/email-templates/{$template->id}")->assertOk();
        $this->assertNull(AutomationEmailTemplate::find($template->id));
    }

    public function test_a_second_template_with_the_same_name_conflicts(): void
    {
        $this->template(['name' => 'Overdue reminder']);

        $this->apiPost('/automation/email-templates', [
            'name' => 'Overdue reminder',
            'subject' => 'Again',
            'body_html' => '<p>Again</p>',
        ])->assertStatus(409);

        $this->assertSame(1, AutomationEmailTemplate::count());
    }

    private function template(array $attributes = []): AutomationEmailTemplate
    {
        return AutomationEmailTemplate::factory()->create([
            'organization_id' => $this->organization->id,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
