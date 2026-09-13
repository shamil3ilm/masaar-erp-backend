<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Models\Core\ImportJob;
use App\Models\Sales\Contact;
use App\Services\Core\Importers\ContactImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * Re-importing a contact must update it, not duplicate it.
 *
 * The importer looked for an existing contact with where('tax_number', ...),
 * but tax_number is encrypted with a fresh IV on every write, so that
 * comparison could never match and every re-import created a second contact.
 */
class ContactImporterTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_a_row_matched_only_by_tax_number_updates_the_existing_contact(): void
    {
        $existing = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => 'customer',
            'company_name' => 'Original Name',
            'email' => 'original@example.com',
            'tax_number' => '300000000000003',
        ]);

        $job = ImportJob::factory()->create([
            'organization_id' => $this->organization->id,
            'entity_type' => ImportJob::ENTITY_CUSTOMERS,
        ]);

        $result = (new ContactImporter)->importRow([
            'company_name' => 'Renamed Trading',
            'contact_name' => 'Someone',
            'email' => 'renamed@example.com',
            'tax_number' => '300-000000-000003',
        ], $job, ['update_existing' => true]);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame('Renamed Trading', $existing->fresh()->company_name);
        $this->assertSame(1, Contact::where('organization_id', $this->organization->id)->count());
    }

    public function test_a_row_with_a_different_tax_number_creates_a_new_contact(): void
    {
        Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'contact_type' => 'customer',
            'company_name' => 'First',
            'email' => 'first@example.com',
            'tax_number' => '300000000000003',
        ]);

        $job = ImportJob::factory()->create([
            'organization_id' => $this->organization->id,
            'entity_type' => ImportJob::ENTITY_CUSTOMERS,
        ]);

        (new ContactImporter)->importRow([
            'company_name' => 'Second',
            'contact_name' => 'Someone',
            'email' => 'second@example.com',
            'tax_number' => '300000000000004',
        ], $job, ['update_existing' => true]);

        $this->assertSame(2, Contact::where('organization_id', $this->organization->id)->count());
    }

    public function test_a_new_contact_keeps_its_billing_country(): void
    {
        $job = ImportJob::factory()->create([
            'organization_id' => $this->organization->id,
            'entity_type' => ImportJob::ENTITY_CUSTOMERS,
        ]);

        // billing_country was written to a column that does not exist, which
        // outside production threw on every new contact and in production
        // silently lost the country.
        $contact = (new ContactImporter)->importRow([
            'company_name' => 'Gulf Supplies',
            'contact_name' => 'Someone',
            'billing_country_code' => 'sa',
        ], $job);

        $this->assertSame('SA', $contact->fresh()->billing_country_code);
    }

    public function test_a_country_that_is_not_a_two_letter_code_is_refused(): void
    {
        $job = ImportJob::factory()->create([
            'organization_id' => $this->organization->id,
            'entity_type' => ImportJob::ENTITY_CUSTOMERS,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('two-letter code');

        (new ContactImporter)->importRow([
            'company_name' => 'Gulf Supplies',
            'contact_name' => 'Someone',
            'billing_country_code' => 'Saudi Arabia',
        ], $job);
    }

    public function test_a_row_with_only_a_company_name_imports(): void
    {
        $job = ImportJob::factory()->create([
            'organization_id' => $this->organization->id,
            'entity_type' => ImportJob::ENTITY_CUSTOMERS,
        ]);

        // contact_name is required by the column but optional in the template,
        // and credit_limit was written as an explicit null over its NOT NULL
        // default. Either one stopped a new contact from importing.
        $contact = (new ContactImporter)->importRow([
            'company_name' => 'Gulf Supplies',
        ], $job);

        $fresh = $contact->fresh();
        $this->assertSame('Gulf Supplies', $fresh->contact_name);
        $this->assertSame('0.0000', (string) $fresh->credit_limit);
    }
}
