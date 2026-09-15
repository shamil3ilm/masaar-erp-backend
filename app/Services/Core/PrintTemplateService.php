<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Core\PrintConfiguration;
use App\Models\Core\PrintTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * An organization's print templates and printer configurations.
 *
 * One template is the default per document type and paper size, and one
 * configuration per branch. Marking a record default clears the others in
 * the same transaction, so a failed write cannot leave the organization
 * without a default or with two.
 */
class PrintTemplateService
{
    /**
     * Active templates of the organization by document type and paper size,
     * grouped by document type.
     */
    public function listActiveTemplates(int $organizationId): \Illuminate\Support\Collection
    {
        return PrintTemplate::where('organization_id', $organizationId)
            ->active()
            ->orderBy('document_type')
            ->orderBy('paper_size')
            ->get()
            ->groupBy('document_type');
    }

    /**
     * A template of the organization by id; another organization's template is not found.
     */
    public function findTemplate(int $organizationId, int $id): PrintTemplate
    {
        return PrintTemplate::where('organization_id', $organizationId)->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data  validated fields with the organization id
     */
    public function createTemplate(array $data): PrintTemplate
    {
        return DB::transaction(function () use ($data): PrintTemplate {
            if ($data['is_default'] ?? false) {
                PrintTemplate::where('organization_id', $data['organization_id'])
                    ->where('document_type', $data['document_type'])
                    ->where('paper_size', $data['paper_size'])
                    ->update(['is_default' => false]);
            }

            return PrintTemplate::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated fields
     */
    public function updateTemplate(PrintTemplate $template, array $data): PrintTemplate
    {
        return DB::transaction(function () use ($template, $data): PrintTemplate {
            if ($data['is_default'] ?? false) {
                PrintTemplate::where('organization_id', $template->organization_id)
                    ->where('document_type', $template->document_type)
                    ->where('paper_size', $template->paper_size)
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }

            $template->update($data);

            return $template;
        });
    }

    public function deleteTemplate(PrintTemplate $template): void
    {
        $template->delete();
    }

    /**
     * Creates the built-in templates the organization does not have yet, the
     * first one created as default, and returns how many were created.
     */
    public function initializeDefaults(int $organizationId): int
    {
        return DB::transaction(function () use ($organizationId): int {
            $existingCodes = PrintTemplate::where('organization_id', $organizationId)
                ->pluck('code')
                ->toArray();

            $created = 0;

            foreach (PrintTemplate::DEFAULT_TEMPLATES as $template) {
                if (!in_array($template['code'], $existingCodes)) {
                    PrintTemplate::create(array_merge($template, [
                        'organization_id' => $organizationId,
                        'is_default' => $created === 0, // First one is default
                    ]));
                    $created++;
                }
            }

            return $created;
        });
    }

    /**
     * @return Collection<int, PrintConfiguration>
     */
    public function listConfigurations(int $organizationId): Collection
    {
        return PrintConfiguration::where('organization_id', $organizationId)
            ->with('branch')
            ->get();
    }

    /**
     * A configuration of the organization by id; another organization's configuration is not found.
     */
    public function findConfiguration(int $organizationId, int $id): PrintConfiguration
    {
        return PrintConfiguration::where('organization_id', $organizationId)->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data  validated fields with the organization id; the branch belongs to it
     */
    public function createConfiguration(array $data): PrintConfiguration
    {
        return DB::transaction(function () use ($data): PrintConfiguration {
            if ($data['is_default'] ?? false) {
                PrintConfiguration::where('organization_id', $data['organization_id'])
                    ->where('branch_id', $data['branch_id'] ?? null)
                    ->update(['is_default' => false]);
            }

            return PrintConfiguration::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated fields
     */
    public function updateConfiguration(PrintConfiguration $configuration, array $data): PrintConfiguration
    {
        $configuration->update($data);

        return $configuration;
    }
}
