<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Services\Core\CurrencyService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * A configured exchange-rate key has to reach the provider.
 *
 * The service checked that EXCHANGE_RATE_API_KEY was set and then called the
 * keyless v4 endpoint, so the key did nothing.
 */
class CurrencyLiveRateTest extends TestCase
{
    use RefreshDatabase;
    use TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->actingAs($this->user, 'api');
        $this->seed(CurrencySeeder::class);

        config(['services.exchange_rate.api_key' => 'test-exchange-key']);
    }

    public function test_the_rate_is_fetched_with_the_key(): void
    {
        Http::fake(['v6.exchangerate-api.com/*' => Http::response([
            'result' => 'success',
            'base_code' => 'USD',
            'conversion_rates' => ['SAR' => 3.7512],
        ])]);

        $this->assertSame(3.7512, app(CurrencyService::class)->getExchangeRate('USD', 'SAR'));

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-exchange-key')
            && ! str_contains($request->url(), 'test-exchange-key'));
    }

    public function test_a_refused_key_falls_back(): void
    {
        Http::fake(['v6.exchangerate-api.com/*' => Http::response(['result' => 'error', 'error-type' => 'invalid-key'], 403)]);

        $this->assertSame(3.75, app(CurrencyService::class)->getExchangeRate('USD', 'SAR'));
    }
}
