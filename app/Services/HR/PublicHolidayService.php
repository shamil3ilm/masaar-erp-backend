<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Models\HR\Leave\PublicHoliday;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Public holidays of the current organization.
 */
class PublicHolidayService
{
    /**
     * Holidays by date: all of them, or one page when a page size is given.
     *
     * @param  array{year?: mixed, branch_id?: mixed, mandatory_only?: bool}  $filters  empty values are ignored
     */
    public function list(array $filters, ?int $perPage): Collection|LengthAwarePaginator
    {
        $query = PublicHoliday::query()
            ->when($filters['year'] ?? null, fn ($q, $year) => $q->forYear((int) $year))
            ->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->forBranch((int) $id))
            ->when($filters['mandatory_only'] ?? false, fn ($q) => $q->mandatory())
            ->orderBy('holiday_date');

        return $perPage === null ? $query->get() : $query->paginate($perPage);
    }

    public function create(array $data, int $organizationId): PublicHoliday
    {
        return PublicHoliday::create(array_merge($data, ['organization_id' => $organizationId]));
    }

    /**
     * Creates every holiday in the list or none of them.
     *
     * @param  list<array<string, mixed>>  $holidays
     * @return list<PublicHoliday>
     */
    public function createMany(array $holidays, int $organizationId): array
    {
        return DB::transaction(fn (): array => array_map(
            fn (array $holiday): PublicHoliday => $this->create($holiday, $organizationId),
            $holidays
        ));
    }

    public function update(PublicHoliday $holiday, array $data): PublicHoliday
    {
        $holiday->update($data);

        return $holiday->fresh();
    }

    public function delete(PublicHoliday $holiday): void
    {
        $holiday->delete();
    }
}
