<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Core\Organization;
use App\Models\Sales\Contact;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\ShippingRoute;
use App\Models\Sales\ShippingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the shipping zone and route endpoints and route determination, and
 * keeps zones, routes and orders inside the caller's organization.
 */
class ShippingRouteEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrg;
    private ShippingZone $saudi;
    private ShippingZone $emirates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'sales.shipping-routes.view',
            'sales.shipping-routes.manage',
            'sales.shipping-zones.manage',
        ]);

        $this->otherOrg = Organization::factory()->create();
        $this->saudi = $this->zone(['zone_code' => 'SA', 'country_codes' => ['SA']]);
        $this->emirates = $this->zone(['zone_code' => 'AE', 'country_codes' => ['AE']]);
    }

    public function test_zones_are_listed_created_updated_and_deleted(): void
    {
        $inactive = $this->zone(['zone_code' => 'OLD', 'is_active' => false]);
        $this->zone(['organization_id' => $this->otherOrg->id, 'zone_code' => 'X']);

        $all = $this->send('GET', 'sd.shipping-zones.index')->assertOk();
        $this->assertCount(3, $all->json('data'));
        $this->assertSame(
            [$inactive->id],
            array_column($this->send('GET', 'sd.shipping-zones.index', [], ['is_active' => 0])->json('data'), 'id')
        );

        $created = $this->send('POST', 'sd.shipping-zones.store', [], ['zone_code' => 'QA', 'zone_name' => 'Qatar', 'country_codes' => ['QA']]);
        $created->assertStatus(201)->assertJsonPath('data.organization_id', $this->organization->id);
        $id = $created->json('data.id');

        $this->send('PUT', 'sd.shipping-zones.update', ['id' => $id], ['zone_name' => 'State of Qatar'])
            ->assertOk()
            ->assertJsonPath('data.zone_name', 'State of Qatar');
        $this->send('GET', 'sd.shipping-zones.show', ['id' => $id])->assertOk()->assertJsonPath('data.zone_code', 'QA');
        $this->send('DELETE', 'sd.shipping-zones.destroy', ['id' => $id])->assertNoContent();
        $this->assertNull(ShippingZone::find($id));
    }

    public function test_another_organizations_zone_and_route_are_not_found(): void
    {
        $foreignZone = $this->zone(['organization_id' => $this->otherOrg->id, 'zone_code' => 'X']);
        $foreignRoute = $this->route($foreignZone, $foreignZone, ['organization_id' => $this->otherOrg->id]);

        foreach (['show' => 'GET', 'update' => 'PUT', 'destroy' => 'DELETE'] as $action => $method) {
            $this->send($method, "sd.shipping-zones.{$action}", ['id' => $foreignZone->id])->assertNotFound();
            $this->send($method, "sd.shipping-routes.{$action}", ['id' => $foreignRoute->id])->assertNotFound();
        }
    }

    public function test_routes_are_created_with_their_zones_updated_filtered_and_deleted(): void
    {
        $created = $this->send('POST', 'sd.shipping-routes.store', [], [
            'route_code' => 'SA-AE',
            'route_name' => 'Riyadh to Dubai',
            'departure_zone_id' => $this->saudi->id,
            'destination_zone_id' => $this->emirates->id,
            'transportation_mode' => 'road',
            'freight_cost' => 120,
        ]);
        $created->assertStatus(201)
            ->assertJsonPath('data.departure_zone.id', $this->saudi->id)
            ->assertJsonPath('data.destination_zone.id', $this->emirates->id);
        $id = $created->json('data.id');

        $this->route($this->emirates, $this->saudi, ['transportation_mode' => 'air']);

        $this->assertSame(
            [$id],
            array_column($this->send('GET', 'sd.shipping-routes.index', [], ['transportation_mode' => 'road'])->assertOk()->json('data'), 'id')
        );

        $this->send('PUT', 'sd.shipping-routes.update', ['id' => $id], ['transit_days' => 3])
            ->assertOk()
            ->assertJsonPath('data.transit_days', 3)
            ->assertJsonPath('data.destination_zone.id', $this->emirates->id);

        $this->send('GET', 'sd.shipping-routes.show', ['id' => $id])->assertOk()->assertJsonPath('data.departure_zone.id', $this->saudi->id);
        $this->send('DELETE', 'sd.shipping-routes.destroy', ['id' => $id])->assertNoContent();
    }

    public function test_a_route_refuses_another_organizations_zones(): void
    {
        $foreignZone = $this->zone(['organization_id' => $this->otherOrg->id, 'zone_code' => 'X']);

        $created = $this->send('POST', 'sd.shipping-routes.store', [], [
            'route_code' => 'X',
            'route_name' => 'X',
            'departure_zone_id' => $foreignZone->id,
            'destination_zone_id' => $foreignZone->id,
            'transportation_mode' => 'road',
        ]);
        $created->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertErrorsOn($created, ['departure_zone_id', 'destination_zone_id']);

        $route = $this->route($this->saudi, $this->emirates);
        $updated = $this->send('PUT', 'sd.shipping-routes.update', ['id' => $route->id], ['destination_zone_id' => $foreignZone->id]);
        $updated->assertStatus(422);
        $this->assertErrorsOn($updated, ['destination_zone_id']);
    }

    public function test_the_cheapest_or_fastest_route_is_determined(): void
    {
        $cheap = $this->route($this->saudi, $this->emirates, ['route_code' => 'CHEAP', 'freight_cost' => 50, 'transit_days' => 6]);
        $fast = $this->route($this->saudi, $this->emirates, ['route_code' => 'FAST', 'freight_cost' => 200, 'transit_days' => 1]);

        $this->send('POST', 'sd.shipping-routes.determine', [], ['destination_country' => 'AE'])
            ->assertOk()
            ->assertJsonPath('data.id', $cheap->id)
            ->assertJsonPath('data.destination_zone.id', $this->emirates->id);

        $this->send('POST', 'sd.shipping-routes.determine', [], ['destination_country' => 'AE', 'preference' => 'fastest'])
            ->assertOk()
            ->assertJsonPath('data.id', $fast->id);

        $this->send('POST', 'sd.shipping-routes.determine', [], ['destination_country' => 'JP'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.message', 'No matching shipping route found.');
    }

    public function test_a_route_is_determined_for_the_organizations_order_only(): void
    {
        $domestic = $this->route($this->saudi, $this->saudi, ['route_code' => 'DOM']);
        $customer = Contact::factory()->create(['organization_id' => $this->organization->id]);
        $order = SalesOrder::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);

        $this->send('POST', 'sd.shipping-routes.determine-for-order', ['orderId' => $order->id])
            ->assertOk()
            ->assertJsonPath('message', 'Route determined successfully.')
            ->assertJsonPath('data.sales_order_id', $order->id)
            ->assertJsonPath('data.shipping_route.id', $domestic->id);

        $foreign = SalesOrder::factory()->create(['organization_id' => $this->otherOrg->id]);
        $this->send('POST', 'sd.shipping-routes.determine-for-order', ['orderId' => $foreign->id])->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function zone(array $attributes = []): ShippingZone
    {
        $zone = new ShippingZone();
        $zone->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'zone_code' => 'Z',
            'zone_name' => 'Zone',
            'is_active' => true,
        ], $attributes))->save();

        return $zone;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function route(ShippingZone $from, ShippingZone $to, array $attributes = []): ShippingRoute
    {
        $route = new ShippingRoute();
        $route->forceFill(array_merge([
            'organization_id' => $this->organization->id,
            'route_code' => 'R-'.uniqid(),
            'route_name' => 'Route',
            'departure_zone_id' => $from->id,
            'destination_zone_id' => $to->id,
            'transportation_mode' => 'road',
            'transit_days' => 2,
            'freight_cost' => 100,
            'is_active' => true,
        ], $attributes))->save();

        return $route;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $data
     */
    private function send(string $method, string $route, array $params = [], array $data = []): TestResponse
    {
        $url = route($route, $params);

        if ($method === 'GET' && $data !== []) {
            $url .= '?'.http_build_query($data);
            $data = [];
        }

        return $this->json($method, $url, $data, $this->authHeaders());
    }

    /**
     * @param  list<string>  $fields
     */
    private function assertErrorsOn(TestResponse $response, array $fields): void
    {
        $errors = $response->json('errors') ?? $response->json('error.details') ?? [];

        foreach ($fields as $field) {
            $this->assertArrayHasKey($field, $errors, 'No validation error on '.$field.': '.json_encode($response->json()));
        }
    }
}
