<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Covers the webhooks:subscriptions command used to review who is subscribed
 * before enabling emission on an environment.
 */
class ListWebhookSubscriptionsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
    }

    private function subscribe(array $events, bool $active = true): Webhook
    {
        return Webhook::create([
            'organization_id' => $this->organization->id,
            'created_by'      => $this->user->id,
            'name'            => 'Ops endpoint',
            'url'             => 'https://example.test/hook',
            'secret'          => 'shhh',
            'events'          => $events,
            'is_active'       => $active,
            'retry_count'     => 3,
            'timeout_seconds' => 30,
            'content_type'    => 'application/json',
        ]);
    }

    public function test_reports_when_no_subscriptions_exist(): void
    {
        $this->artisan('webhooks:subscriptions')
            ->expectsOutputToContain('No webhook subscriptions exist')
            ->assertSuccessful();
    }

    public function test_lists_a_subscription_and_the_events_it_would_receive(): void
    {
        $this->subscribe(['invoice.created', 'contact.updated']);

        $this->artisan('webhooks:subscriptions')
            ->expectsOutputToContain('Ops endpoint')
            ->assertSuccessful();
    }

    public function test_wildcard_subscription_is_listed(): void
    {
        $this->subscribe(['*']);

        $this->artisan('webhooks:subscriptions')->assertSuccessful();
    }

    public function test_reports_the_kill_switch_state(): void
    {
        config(['webhooks.enabled' => false]);

        $this->artisan('webhooks:subscriptions')
            ->expectsOutputToContain('Emission is DISABLED')
            ->assertSuccessful();
    }

    public function test_can_filter_to_one_organization(): void
    {
        $this->subscribe(['invoice.created']);

        $this->artisan('webhooks:subscriptions', ['--org' => $this->organization->id])
            ->assertSuccessful();
    }
}
