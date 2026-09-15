<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Inventory\Product;
use App\Models\Sales\Contact;
use App\Models\Sales\PriceList;
use App\Models\Sales\PriceListAssignment;
use App\Models\Sales\PriceListItem;
use App\Models\Sales\PriceVolumeBreak;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PriceListService
{
    /**
     * Price lists of the current organization, latest first, narrowed by the
     * filters that are set. valid_now keeps lists in effect today.
     *
     * @param  array{is_active?: ?bool, currency_code?: ?string, search?: ?string, valid_now?: bool}  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return PriceList::query()->latest()
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->when(isset($filters['currency_code']), fn ($q) => $q->where('currency_code', $filters['currency_code']))
            ->when(isset($filters['search']), function ($q) use ($filters) {
                $search = $filters['search'];
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['valid_now'] ?? false, function ($q) {
                $today = now()->toDateString();
                $q->where('valid_from', '<=', $today)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
                    });
            })
            ->paginate($perPage);
    }

    /**
     * A price list with its items, assignments and volume breaks.
     *
     * @return array{price_list: PriceList, items: Collection, assignments: Collection, volume_breaks: Collection}
     */
    public function details(PriceList $priceList): array
    {
        return [
            'price_list' => $priceList,
            'items' => PriceListItem::where('price_list_id', $priceList->id)->get(),
            'assignments' => PriceListAssignment::where('price_list_id', $priceList->id)->get(),
            'volume_breaks' => PriceVolumeBreak::where('price_list_id', $priceList->id)->get(),
        ];
    }

    /**
     * Create a new price list together with optional line items.
     *
     * The request names the end date valid_to; it is stored as valid_until.
     *
     * @param  array<string, mixed>  $data  validated price list fields, with organization_id and optional items
     */
    public function createPriceList(array $data): PriceList
    {
        return DB::transaction(function () use ($data) {
            $priceList = PriceList::create($this->headerAttributes($data));

            if (! empty($data['items'])) {
                $this->syncItems($priceList, $data['items']);
            }

            return $priceList->fresh();
        });
    }

    /**
     * Update an existing price list and, when items are given, replace them.
     *
     * @param  array<string, mixed>  $data  validated price list fields with optional items
     */
    public function updatePriceList(PriceList $priceList, array $data): PriceList
    {
        return DB::transaction(function () use ($priceList, $data) {
            $priceList->update($this->headerAttributes($data));

            if (($data['items'] ?? null) !== null) {
                $this->syncItems($priceList, $data['items']);
            }

            return $priceList->fresh();
        });
    }

    /**
     * Resolve the effective price for a contact and product of the current
     * organization.
     *
     * @return array{unit_price:float,discount_pct:float,effective_price:float,source:string,price_list_id:int}|null
     */
    public function resolvePriceFor(int $contactId, int $productId, float $quantity, ?string $currency): ?array
    {
        return $this->resolvePrice(
            Contact::findOrFail($contactId),
            Product::findOrFail($productId),
            $quantity,
            $currency
        );
    }

    /**
     * Resolve the best unit price for a contact/product/qty/currency combination.
     *
     * Resolution order:
     *   1. Customer-specific price list (assignment_type = contact)
     *   2. Customer-group price list (assignment_type = customer_group)
     *   3. Default / "all" price list
     *   4. Volume-break tier from any of the above
     *
     * Returns an array with resolved price details or null when no price found.
     *
     * @return array{unit_price:float,discount_pct:float,effective_price:float,source:string,price_list_id:int}|null
     */
    public function resolvePrice(
        Contact $contact,
        Product $product,
        float $quantity = 1.0,
        ?string $currency = null
    ): ?array {
        $currency = $currency ?? $contact->currency_code ?? 'SAR';
        $today    = now()->toDateString();

        // Retrieve all active price lists of the contact's organization in effect today.
        $assignmentRows = DB::table('price_list_assignments as pla')
            ->join('price_lists as pl', 'pl.id', '=', 'pla.price_list_id')
            ->where('pl.organization_id', $contact->organization_id)
            ->where('pl.is_active', true)
            ->where('pl.currency_code', $currency)
            ->where('pl.valid_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('pl.valid_until')->orWhere('pl.valid_until', '>=', $today);
            })
            ->select('pla.*', 'pl.id as pl_id')
            ->orderByDesc('pla.priority')
            ->get();

        // Determine the ordered candidate price list IDs based on assignment priority.
        $candidateListIds = $this->rankCandidateLists($assignmentRows, $contact);

        foreach ($candidateListIds as ['list_id' => $listId, 'source' => $source]) {
            // Try volume breaks first.
            $break = PriceVolumeBreak::where('price_list_id', $listId)
                ->where('product_id', $product->id)
                ->where('min_qty', '<=', $quantity)
                ->where(function ($q) use ($quantity) {
                    $q->whereNull('max_qty')->orWhere('max_qty', '>=', $quantity);
                })
                ->orderByDesc('min_qty')
                ->first();

            if ($break !== null) {
                return $this->buildPriceResult(
                    (float) $break->unit_price,
                    (float) $break->discount_pct,
                    $listId,
                    $source . '.volume_break'
                );
            }

            // Then try standard item price.
            $item = PriceListItem::where('price_list_id', $listId)
                ->where('product_id', $product->id)
                ->where('min_quantity', '<=', $quantity)
                ->orderByDesc('min_quantity')
                ->first();

            if ($item !== null) {
                return $this->buildPriceResult(
                    (float) $item->unit_price,
                    (float) $item->discount_percent,
                    $listId,
                    $source
                );
            }
        }

        return null;
    }

    /**
     * Assign a price list to a contact of the current organization.
     */
    public function assignToContactId(PriceList $priceList, int $contactId): PriceListAssignment
    {
        return $this->assignToContact($priceList, Contact::findOrFail($contactId));
    }

    /**
     * Assign a price list to a specific contact, replacing the contact's
     * assignment to any other list of the organization. The removal and the
     * new assignment happen in one transaction, so the contact is never left
     * without its list.
     */
    public function assignToContact(PriceList $priceList, Contact $contact): PriceListAssignment
    {
        return DB::transaction(function () use ($priceList, $contact): PriceListAssignment {
            PriceListAssignment::whereIn(
                'price_list_id',
                PriceList::where('organization_id', $priceList->organization_id)->pluck('id')
            )
                ->where('assignment_type', PriceListAssignment::TYPE_CONTACT)
                ->where('assignment_id', $contact->id)
                ->delete();

            return PriceListAssignment::create([
                'price_list_id'   => $priceList->id,
                'assignment_type' => PriceListAssignment::TYPE_CONTACT,
                'assignment_id'   => $contact->id,
                'priority'        => 100,
            ]);
        });
    }

    /**
     * Bulk-import price list items, upserted by product and minimum quantity.
     *
     * @param  array<int,array{product_id:int,unit_price:float,min_quantity?:float,discount_pct?:float}>  $rows
     */
    public function importItems(PriceList $priceList, array $rows): int
    {
        return DB::transaction(function () use ($priceList, $rows): int {
            foreach ($rows as $row) {
                $attributes = $this->itemAttributes($row);

                PriceListItem::updateOrCreate(
                    [
                        'price_list_id' => $priceList->id,
                        'product_id'    => $attributes['product_id'],
                        'min_quantity'  => $attributes['min_quantity'],
                    ],
                    Arr::only($attributes, ['unit_price', 'discount_percent'])
                );
            }

            return count($rows);
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * The price list columns from request fields: valid_to is stored as
     * valid_until, and the items are saved separately.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function headerAttributes(array $data): array
    {
        if (array_key_exists('valid_to', $data)) {
            $data['valid_until'] = $data['valid_to'];
        }

        return Arr::except($data, ['valid_to', 'items']);
    }

    /**
     * The item columns from a request row. The request names the discount
     * discount_pct; it is stored as discount_percent and must lie in 0..100.
     *
     * @param  array<string, mixed>  $row
     * @return array{product_id: int, unit_price: mixed, min_quantity: mixed, discount_percent: mixed}
     */
    private function itemAttributes(array $row): array
    {
        $discountPct = $row['discount_pct'] ?? 0;
        if (bccomp((string) $discountPct, '0', 4) < 0 || bccomp((string) $discountPct, '100', 4) > 0) {
            throw new \InvalidArgumentException('Discount percentage must be between 0 and 100.');
        }

        return [
            'product_id'       => (int) $row['product_id'],
            'unit_price'       => $row['unit_price'],
            'min_quantity'     => $row['min_quantity'] ?? 1,
            'discount_percent' => $discountPct,
        ];
    }

    /**
     * Rank candidate price list IDs by assignment priority for the contact.
     *
     * @return array<int,array{list_id:int,source:string}>
     */
    private function rankCandidateLists(
        Collection $assignmentRows,
        Contact $contact
    ): array {
        $candidates = [];

        foreach ($assignmentRows as $row) {
            if (
                $row->assignment_type === PriceListAssignment::TYPE_CONTACT
                && (int) $row->assignment_id === $contact->id
            ) {
                $candidates[] = ['list_id' => (int) $row->pl_id, 'source' => 'contact', 'sort' => (int) $row->priority + 1000];
                continue;
            }

            if (
                $row->assignment_type === PriceListAssignment::TYPE_CUSTOMER_GROUP
                && $contact->customer_group_id
                && (int) $row->assignment_id === (int) $contact->customer_group_id
            ) {
                $candidates[] = ['list_id' => (int) $row->pl_id, 'source' => 'customer_group', 'sort' => (int) $row->priority + 500];
                continue;
            }

            if ($row->assignment_type === PriceListAssignment::TYPE_ALL) {
                $candidates[] = ['list_id' => (int) $row->pl_id, 'source' => 'all', 'sort' => (int) $row->priority];
            }
        }

        usort($candidates, static fn ($a, $b) => $b['sort'] <=> $a['sort']);

        return $candidates;
    }

    /**
     * Replace a price list's items with the given rows.
     *
     * @param  array<int,array<string, mixed>>  $items
     */
    private function syncItems(PriceList $priceList, array $items): void
    {
        PriceListItem::where('price_list_id', $priceList->id)->delete();

        foreach ($items as $item) {
            PriceListItem::create(array_merge($this->itemAttributes($item), [
                'price_list_id' => $priceList->id,
            ]));
        }
    }

    /**
     * Build a standardised price resolution result array.
     */
    private function buildPriceResult(
        float $unitPrice,
        float $discountPct,
        int $priceListId,
        string $source
    ): array {
        $discountAmount  = bcdiv(bcmul((string) $unitPrice, (string) $discountPct, 4), '100', 4);
        $effectivePrice  = bcsub((string) $unitPrice, $discountAmount, 4);

        return [
            'unit_price'      => $unitPrice,
            'discount_pct'    => $discountPct,
            'effective_price' => (float) $effectivePrice,
            'source'          => $source,
            'price_list_id'   => $priceListId,
        ];
    }
}
