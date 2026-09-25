<?php

declare(strict_types=1);

namespace Tests\Feature\Tax;

use App\Models\Core\Organization;
use App\Models\Tax\TaxCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Tax determination rules: which tax type applies to a document line, and
 * the preview that shows which rule a line would match.
 */
class TaxDeterminationEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Organization $otherOrganization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization('SA');
        $this->setUpAuthenticatedUser(['tax.determination-rules.view', 'tax.determination-rules.manage']);
        $this->otherOrganization = Organization::factory()->create();
    }

    public function test_a_rule_is_created_shown_and_matched_by_the_simulation(): void
    {
        $category = TaxCategory::factory()->create(['organization_id' => $this->organization->id]);

        $rule = $this->apiPost('/tax-determination-rules', $this->rulePayload($category->id))
            ->assertCreated()
            ->assertJsonPath('message', 'Tax determination rule created.')
            ->assertJsonPath('data.tax_category.id', $category->id)
            ->json('data');

        $this->apiGet('/tax-determination-rules/'.$rule['uuid'])
            ->assertOk()
            ->assertJsonPath('data.id', $rule['id']);

        $this->apiPost('/tax-determination-rules/simulate', [
            'document_type' => 'sales_invoice',
            'tax_category_id' => $category->id,
            'amount' => 100,
        ])->assertOk()
            ->assertJsonPath('data.matched_rule.id', $rule['id'])
            ->assertJsonPath('data.matched_rule.tax_category.id', $category->id);
    }

    public function test_a_rule_refuses_a_tax_category_of_another_organization(): void
    {
        $foreign = TaxCategory::factory()->create(['organization_id' => $this->otherOrganization->id]);

        $this->apiPost('/tax-determination-rules', $this->rulePayload($foreign->id))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->apiPost('/tax-determination-rules/simulate', [
            'document_type' => 'sales_invoice',
            'tax_category_id' => $foreign->id,
            'amount' => 100,
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, DB::table('tax_determination_rules')->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function rulePayload(int $categoryId): array
    {
        return [
            'name' => 'Domestic standard rate',
            'document_type' => 'sales_invoice',
            'tax_category_id' => $categoryId,
            'tax_type' => 'standard',
            'priority' => 10,
            'is_active' => true,
        ];
    }
}
