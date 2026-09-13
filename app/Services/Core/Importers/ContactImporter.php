<?php

declare(strict_types=1);

namespace App\Services\Core\Importers;

use App\Models\Core\ImportJob;
use App\Models\Sales\Contact;
use App\Services\Core\ImporterInterface;

class ContactImporter implements ImporterInterface
{
    public function importRow(array $data, ImportJob $importJob, array $options = []): mixed
    {
        $contactType = match ($importJob->entity_type) {
            ImportJob::ENTITY_CUSTOMERS => 'customer',
            ImportJob::ENTITY_SUPPLIERS => 'supplier',
            default => 'customer',
        };

        // Check for existing contact
        $existing = null;
        if ($options['update_existing'] ?? false) {
            $existing = Contact::where('organization_id', $importJob->organization_id)
                ->where('contact_type', $contactType)
                ->where(function ($query) use ($data) {
                    if (! empty($data['email'])) {
                        $query->orWhere('email', $data['email']);
                    }
                    // tax_number is encrypted; match on its hash.
                    $taxHash = Contact::taxNumberHash($data['tax_number'] ?? null);
                    if ($taxHash !== null) {
                        $query->orWhere('tax_number_hash', $taxHash);
                    }
                    if (! empty($data['company_name'])) {
                        $query->orWhere('company_name', $data['company_name']);
                    }
                })
                ->first();
        }

        $contactData = [
            'organization_id' => $importJob->organization_id,
            'contact_type' => $contactType,
            'company_name' => $data['company_name'] ?? null,
            // The column requires a name; the template does not. Company name is
            // the one field every row must have.
            'contact_name' => $data['contact_name'] ?? $data['company_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'tax_number' => $data['tax_number'] ?? null,
            'currency_code' => $data['currency_code'] ?? 'SAR',
            'payment_terms' => $data['payment_terms'] ?? 30,
            'credit_limit' => $data['credit_limit'] ?? null,
            'billing_address_line_1' => $data['billing_address_line_1'] ?? null,
            'billing_address_line_2' => $data['billing_address_line_2'] ?? null,
            'billing_city' => $data['billing_city'] ?? null,
            'billing_state' => $data['billing_state'] ?? null,
            'billing_postal_code' => $data['billing_postal_code'] ?? null,
            'billing_country_code' => $this->countryCode($data['billing_country_code'] ?? null),
            'is_active' => true,
        ];

        if ($existing) {
            $existing->update(array_filter($contactData, fn ($v) => $v !== null));

            return $existing;
        }

        // Leave out what the row did not supply so column defaults apply. An
        // explicit null for credit_limit overrode its NOT NULL default of 0.
        return Contact::create(array_filter($contactData, fn ($v) => $v !== null));
    }

    /**
     * A two-letter country code, or null when none was given.
     *
     * The column holds exactly two letters. Anything else is refused rather
     * than truncated or dropped, and ImportService records the message against
     * the row, so the report says which one to fix.
     */
    private function countryCode(?string $value): ?string
    {
        $code = strtoupper(trim((string) $value));

        if ($code === '') {
            return null;
        }

        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            throw new \InvalidArgumentException("Billing country must be a two-letter code such as SA, not \"{$value}\".");
        }

        return $code;
    }
}
