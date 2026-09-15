<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\ScrapReport;
use App\Services\Manufacturing\ScrapReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\BuildsOtherOrganizationInventory;
use Tests\Traits\TestHelpers;

/**
 * Scrap reports accept only the organization's own rows and reach the GL once.
 */
class ScrapReportPostingTest extends TestCase
{
    use BuildsOtherOrganizationInventory, RefreshDatabase, TestHelpers;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser([
            'manufacturing.production.manage',
            'manufacturing.production.view',
        ]);

        $this->product = Product::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_another_organizations_user_cannot_be_named_as_reporter(): void
    {
        $response = $this->apiPost('/manufacturing/scrap-reports', [
            'product_id' => $this->product->id,
            'scrap_date' => now()->toDateString(),
            'scrap_quantity' => 2,
            'reported_by' => $this->foreignUser()->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['reported_by']);
    }

    public function test_a_stale_gl_posting_is_refused(): void
    {
        $report = $this->report();
        $stale = ScrapReport::findOrFail($report->id);
        $service = app(ScrapReportingService::class);

        $service->postToGL(ScrapReport::findOrFail($report->id));

        $this->expectException(ValidationException::class);
        $service->postToGL($stale);
    }

    public function test_a_report_posted_to_gl_cannot_be_deleted(): void
    {
        $report = $this->report(['gl_posted' => true, 'gl_posted_at' => now()]);

        $this->apiDelete("/manufacturing/scrap-reports/{$report->id}")->assertStatus(422);

        $this->assertNotSoftDeleted('scrap_reports', ['id' => $report->id]);
    }

    public function test_another_organizations_report_is_not_found(): void
    {
        $theirs = ScrapReport::factory()->create([
            'organization_id' => $this->otherOrganization()->id,
            'product_id' => $this->foreignProduct()->id,
        ]);

        $this->apiGet("/manufacturing/scrap-reports/{$theirs->id}")->assertNotFound();
        $this->apiPost("/manufacturing/scrap-reports/{$theirs->id}/post-gl")->assertNotFound();
        $this->apiDelete("/manufacturing/scrap-reports/{$theirs->id}")->assertNotFound();

        $this->assertFalse((bool) $theirs->fresh()->gl_posted);
    }

    private function report(array $attributes = []): ScrapReport
    {
        return ScrapReport::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'product_id' => $this->product->id,
            'reported_by' => $this->user->id,
        ], $attributes));
    }
}
