<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\Contact;
use App\Models\Sales\QuickSaleTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class QuickSaleService
{
    public function __construct() {}

    /**
     * Quick sale templates of the current organization with the reference
     * columns of their default customer, newest first. Each filter applies
     * when its key is present.
     *
     * @param  array{is_active?: bool, search?: mixed}  $filters
     */
    public function listTemplates(array $filters, int $perPage): LengthAwarePaginator
    {
        return QuickSaleTemplate::with(['defaultCustomer:'.implode(',', Contact::REFERENCE_COLUMNS)])
            ->latest()
            ->when(array_key_exists('is_active', $filters), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when(array_key_exists('search', $filters), fn ($q) => $q->search($filters['search']))
            ->paginate($perPage);
    }

    /**
     * Create a quick sale template.
     */
    public function createTemplate(array $data): QuickSaleTemplate
    {
        return DB::transaction(function () use ($data) {
            $data['organization_id'] = $data['organization_id'] ?? auth()->user()->organization_id;
            $data['is_active'] = $data['is_active'] ?? true;

            return QuickSaleTemplate::create($data);
        });
    }

    /**
     * Use a template to pre-populate a bulk sale batch.
     */
    public function useTemplate(QuickSaleTemplate $template): array
    {
        if (!$template->is_active) {
            throw new \InvalidArgumentException('Template is inactive.');
        }

        return [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'default_customer_id' => $template->default_customer_id,
            'default_payment_method' => $template->default_payment_method,
            'items' => $template->default_items ?? [],
        ];
    }

    /**
     * Duplicate a template.
     */
    public function duplicateTemplate(QuickSaleTemplate $template, ?string $newName = null): QuickSaleTemplate
    {
        return DB::transaction(function () use ($template, $newName) {
            $data = $template->toArray();

            unset($data['id'], $data['created_at'], $data['updated_at']);

            $data['name'] = $newName ?? $template->name . ' (Copy)';

            return QuickSaleTemplate::create($data);
        });
    }
}
