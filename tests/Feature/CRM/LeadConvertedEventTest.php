<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Events\CRM\LeadConverted;
use App\Models\Core\Notification;
use App\Models\CRM\Lead;
use App\Models\CRM\PipelineStage;
use App\Services\CRM\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class LeadConvertedEventTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();

        PipelineStage::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_converting_a_lead_dispatches_lead_converted(): void
    {
        Event::fake([LeadConverted::class]);

        $result = app(LeadService::class)->convert($this->qualifiedLead(), $this->user->id);

        Event::assertDispatched(LeadConverted::class, fn (LeadConverted $event) => $event->lead->id === $result['lead']->id
            && $event->lead->status === Lead::STATUS_CONVERTED
            && $event->contact->id === $result['contact']->id
            && $event->opportunity?->id === $result['opportunity']->id);
    }

    public function test_converting_without_an_opportunity_dispatches_none(): void
    {
        Event::fake([LeadConverted::class]);

        app(LeadService::class)->convert($this->qualifiedLead(), $this->user->id, false);

        Event::assertDispatched(LeadConverted::class, fn (LeadConverted $event) => $event->opportunity === null);
    }

    public function test_lead_conversion_notifies_the_assignee(): void
    {
        $result = app(LeadService::class)->convert($this->qualifiedLead(), $this->user->id);

        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', 'lead_converted')
            ->firstOrFail();

        $this->assertSame($result['contact']->getDisplayName(), $notification->data['contact_name']);
        $this->assertSame($result['opportunity']->id, $notification->data['opportunity_id']);
    }

    private function qualifiedLead(): Lead
    {
        return Lead::factory()->qualified()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Warehouse expansion',
            'company_name' => 'Initech',
            'assigned_to' => $this->user->id,
            'estimated_value' => 10000,
            'currency_code' => 'SAR',
        ]);
    }
}
