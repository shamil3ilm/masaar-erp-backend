<?php

declare(strict_types=1);

namespace App\Services\Core\Importers;

use App\Models\Accounting\Account;
use App\Models\Core\ImportJob;
use App\Services\Core\ImporterInterface;

class ChartOfAccountImporter implements ImporterInterface
{
    public function importRow(array $data, ImportJob $importJob, array $options = []): mixed
    {
        // Check for existing account
        $existing = Account::where('organization_id', $importJob->organization_id)
            ->where('code', $data['code'])
            ->first();

        if ($existing && ! ($options['update_existing'] ?? false)) {
            // Skip existing if not updating
            return $existing;
        }

        // Resolve parent account
        $parentId = null;
        if (! empty($data['parent_code'])) {
            $parent = Account::where('organization_id', $importJob->organization_id)
                ->where('code', $data['parent_code'])
                ->first();
            $parentId = $parent?->id;
        }

        // Validate type
        $validTypes = ['asset', 'liability', 'equity', 'income', 'expense'];
        $type = strtolower($data['type'] ?? 'asset');
        if (! in_array($type, $validTypes)) {
            throw new \InvalidArgumentException("Invalid account type: {$data['type']}. Must be one of: ".implode(', ', $validTypes));
        }

        $accountData = [
            'organization_id' => $importJob->organization_id,
            'code' => $data['code'],
            'name' => $data['name'],
            'account_type' => $type,
            'sub_type' => $this->subType($type, $data['sub_type'] ?? null),
            'parent_id' => $parentId,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_system' => false,
        ];

        if ($existing) {
            $existing->update($accountData);

            return $existing;
        }

        return Account::create($accountData);
    }

    /** The sub-types the column allows, by account type. */
    private const SUB_TYPES = [
        'asset' => ['cash', 'bank', 'receivable', 'inventory', 'fixed_asset', 'other_asset'],
        'liability' => ['payable', 'credit_card', 'tax_payable', 'other_liability'],
        'equity' => ['capital', 'retained_earnings', 'drawings'],
        'income' => ['sales', 'other_income'],
        'expense' => ['cost_of_goods', 'operating_expense', 'other_expense'],
    ];

    /**
     * The column requires a sub-type, from a fixed list per type.
     *
     * A row that gives none gets its type's catch-all - equity has no "other",
     * so capital. One that names something outside the list is refused, and
     * ImportService records the message against the row.
     */
    private function subType(string $type, ?string $given): string
    {
        $subType = strtolower(trim((string) $given));

        if ($subType === '') {
            return $type === 'equity' ? 'capital' : "other_{$type}";
        }

        if (! in_array($subType, self::SUB_TYPES[$type], true)) {
            throw new \InvalidArgumentException(
                "Invalid sub type for {$type}: {$given}. Must be one of: ".implode(', ', self::SUB_TYPES[$type])
            );
        }

        return $subType;
    }
}
