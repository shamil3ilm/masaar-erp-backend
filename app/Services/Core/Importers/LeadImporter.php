<?php

declare(strict_types=1);

namespace App\Services\Core\Importers;

use App\Models\Core\ImportJob;
use App\Models\CRM\Lead;
use App\Models\CRM\LeadSource;
use App\Services\Core\ImporterInterface;

class LeadImporter implements ImporterInterface
{
    public function importRow(array $data, ImportJob $importJob, array $options = []): mixed
    {
        // Check for existing lead
        $existing = null;
        if ($options['update_existing'] ?? false) {
            $existing = Lead::where('organization_id', $importJob->organization_id)
                ->where(function ($query) use ($data) {
                    if (! empty($data['email'])) {
                        $query->orWhere('email', $data['email']);
                    }
                    if (! empty($data['company_name'])) {
                        $query->orWhere('company_name', $data['company_name']);
                    }
                })
                ->first();
        }

        $leadData = [
            'organization_id' => $importJob->organization_id,
            'company_name' => $data['company_name'],
            // The column requires a name; the template does not.
            'contact_name' => $data['contact_name'] ?? $data['company_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'lead_source_id' => $this->leadSourceId($importJob, $data['source'] ?? null),
            'status' => $this->status($data['status'] ?? null),
            'industry' => $data['industry'] ?? null,
            'estimated_value' => $data['estimated_value'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $importJob->user_id,
        ];

        if ($existing) {
            $existing->update(array_filter($leadData, fn ($v) => $v !== null));

            return $existing;
        }

        // Leave out what the row did not supply so column defaults apply.
        return Lead::create(array_filter($leadData, fn ($v) => $v !== null));
    }

    /** The values the status column allows. */
    private const STATUSES = ['new', 'contacted', 'qualified', 'unqualified', 'converted', 'lost'];

    private function status(?string $given): string
    {
        $status = strtolower(trim((string) $given));

        if ($status === '') {
            return 'new';
        }

        if (! in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(
                "Invalid lead status: {$given}. Must be one of: ".implode(', ', self::STATUSES)
            );
        }

        return $status;
    }

    /**
     * The lead source a name refers to, created on first use.
     *
     * Leads have no source column: they point at a lead source. The importer
     * wrote a free-text source there, which the model refused. A row that
     * names none gets no source, rather than one called "import".
     */
    private function leadSourceId(ImportJob $importJob, ?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return LeadSource::firstOrCreate(
            ['organization_id' => $importJob->organization_id, 'name' => $name],
            ['is_active' => true],
        )->id;
    }
}
