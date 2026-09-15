<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\OnboardingStep;
use App\Models\Core\OnboardingTemplate;
use App\Models\Core\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the onboarding endpoints. An organization sees its own templates and
 * the platform's shared ones, reads progress and completes steps only against
 * those, and adds steps only to a template it owns: a shared or another
 * organization's template is not changed.
 */
class OnboardingEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.settings.view', 'core.settings.edit', 'compliance.onboarding.view']);

        $this->otherOrg = Organization::factory()->create();
    }

    public function test_the_organizations_and_shared_templates_are_listed_in_order(): void
    {
        $own = $this->template($this->organization->id, 2);
        $shared = $this->template(null, 1);
        $this->template($this->otherOrg->id, 0);

        $this->assertSame(
            [$shared->id, $own->id],
            array_column($this->apiGet('/onboarding/templates')->assertOk()->json('data'), 'id')
        );
    }

    public function test_a_step_is_added_only_to_the_organizations_own_template(): void
    {
        $own = $this->template($this->organization->id);
        $shared = $this->template(null);
        $foreign = $this->template($this->otherOrg->id);

        $this->apiPost("/onboarding/templates/{$own->id}/steps", ['title' => 'Invite a colleague'])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Step added successfully.')
            ->assertJsonPath('data.template_id', $own->id);

        $this->apiPost("/onboarding/templates/{$shared->id}/steps", ['title' => 'Injected'])->assertNotFound();
        $this->apiPost("/onboarding/templates/{$foreign->id}/steps", ['title' => 'Injected'])->assertNotFound();

        $this->assertSame(0, OnboardingStep::whereIn('template_id', [$shared->id, $foreign->id])->count());
    }

    public function test_progress_and_step_completion_refuse_another_organizations_template(): void
    {
        $own = $this->template($this->organization->id);
        $ownStep = $this->step($own);
        $foreign = $this->template($this->otherOrg->id);
        $foreignStep = $this->step($foreign);

        $this->apiPost("/onboarding/steps/{$ownStep->id}/complete")
            ->assertOk()
            ->assertJsonPath('message', 'Step marked as completed.');

        $this->apiGet("/onboarding/progress/{$this->user->id}?template_id={$own->id}")
            ->assertOk()
            ->assertJsonPath('data.template_id', $own->id)
            ->assertJsonPath('data.steps.0.completed', true);

        $this->apiGet("/onboarding/progress/{$this->user->id}?template_id={$foreign->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('template_id');

        $this->apiPost("/onboarding/steps/{$foreignStep->id}/complete")->assertNotFound();
        $this->apiPost("/onboarding/steps/{$foreignStep->id}/skip")->assertNotFound();
    }

    private function template(?int $organizationId, int $order = 0): OnboardingTemplate
    {
        return OnboardingTemplate::create([
            'organization_id' => $organizationId,
            'name' => 'Template '.($organizationId ?? 'shared').' '.$order,
            'module' => 'core',
            'is_active' => true,
            'order' => $order,
        ]);
    }

    private function step(OnboardingTemplate $template): OnboardingStep
    {
        return OnboardingStep::create([
            'template_id' => $template->id,
            'title' => 'Step',
            'step_type' => 'action',
            'is_required' => true,
            'order' => 1,
        ]);
    }
}
