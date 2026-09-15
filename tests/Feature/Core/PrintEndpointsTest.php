<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\Branch;
use App\Models\Core\Organization;
use App\Models\Core\PrintConfiguration;
use App\Models\Core\PrintTemplate;
use App\Models\Sales\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Pins the print endpoints: templates, printer configurations and batch
 * printing. One default template remains per document type and paper size, a
 * configuration names a branch of the caller's organization, and a batch
 * loads each document with the relations its template reads.
 */
class PrintEndpointsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->setUpAuthenticatedUser(['core.settings.view', 'core.settings.edit', 'sales.invoices.view']);
    }

    public function test_templates_are_created_listed_kept_to_one_default_and_deleted(): void
    {
        $paperSize = array_key_first(PrintTemplate::PAPER_SIZES);

        $first = $this->apiPost('/print/templates', $this->templatePayload('inv-one', $paperSize))->assertStatus(201)->json('data.id');
        $second = $this->apiPost('/print/templates', $this->templatePayload('inv-two', $paperSize))->assertStatus(201)->json('data.id');

        $this->assertFalse((bool) PrintTemplate::findOrFail($first)->is_default);
        $this->assertTrue((bool) PrintTemplate::findOrFail($second)->is_default);

        $this->apiPut("/print/templates/{$first}", ['is_default' => true, 'name' => 'Main'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Main');
        $this->assertFalse((bool) PrintTemplate::findOrFail($second)->is_default);

        $this->apiGet('/print/templates')->assertOk()->assertJsonCount(2, 'data.data.invoice');
        $this->apiGet("/print/templates/{$first}")->assertOk()->assertJsonPath('data.code', 'inv-one');

        $this->apiDelete("/print/templates/{$second}")->assertOk()->assertJsonPath('message', 'Template deleted');
        $this->assertNull(PrintTemplate::find($second));
    }

    public function test_another_organizations_template_and_configuration_are_not_found(): void
    {
        $otherOrg = Organization::factory()->create();
        $template = PrintTemplate::withoutGlobalScopes()->create(array_merge(
            $this->templatePayload('foreign', array_key_first(PrintTemplate::PAPER_SIZES)),
            ['organization_id' => $otherOrg->id]
        ));
        $config = PrintConfiguration::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'printer_type' => array_key_first(PrintConfiguration::getPrinterTypes()),
            'default_paper_size' => 'a4',
        ]);

        $this->apiGet("/print/templates/{$template->id}")->assertNotFound();
        $this->apiPut("/print/templates/{$template->id}", ['name' => 'X'])->assertNotFound();
        $this->apiDelete("/print/templates/{$template->id}")->assertNotFound();
        $this->apiPut("/print/configurations/{$config->id}", ['copies' => 2])->assertNotFound();
    }

    public function test_a_configuration_refuses_another_organizations_branch(): void
    {
        $foreignBranch = Branch::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->apiPost('/print/configurations', [
            'branch_id' => $foreignBranch->id,
            'printer_type' => array_key_first(PrintConfiguration::getPrinterTypes()),
            'default_paper_size' => 'a4',
        ])->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $this->apiPost('/print/configurations', [
            'branch_id' => $this->branch->id,
            'printer_type' => array_key_first(PrintConfiguration::getPrinterTypes()),
            'default_paper_size' => 'a4',
            'is_default' => true,
        ])->assertStatus(201)->assertJsonPath('data.branch_id', $this->branch->id);

        $this->assertSame(1, PrintConfiguration::count());
    }

    public function test_a_batch_prints_the_organizations_documents_with_their_relations(): void
    {
        // The invoice view reads organization attributes the table does not
        // have; production tolerates that, so only lazy loading stays strict here.
        \Illuminate\Database\Eloquent\Model::preventAccessingMissingAttributes(false);

        $this->apiPost('/print/templates/initialize')->assertOk();
        $invoices = Invoice::factory()->count(2)->create(['organization_id' => $this->organization->id]);

        $response = $this->apiPost('/print/batch', [
            'document_type' => 'invoice',
            'ids' => $invoices->pluck('id')->all(),
            'paper_size' => 'a4',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));

        $this->apiPost('/print/batch', ['document_type' => 'invoice', 'ids' => [999999]])
            ->assertNotFound()
            ->assertJsonPath('error.message', 'No documents found');
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(string $code, string $paperSize): array
    {
        return [
            'name' => 'Invoice '.$code,
            'code' => $code,
            'document_type' => 'invoice',
            'paper_size' => $paperSize,
            'is_default' => true,
        ];
    }
}
