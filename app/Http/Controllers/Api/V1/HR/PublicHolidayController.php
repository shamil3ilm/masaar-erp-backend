<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Leave\PublicHoliday;
use App\Services\HR\PublicHolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class PublicHolidayController extends Controller
{
    public function __construct(
        private readonly PublicHolidayService $service,
    ) {}

    /**
     * List public holidays.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->per_page ? (int) $request->per_page : null;

        $holidays = $this->service->list([
            'year'           => $request->year,
            'branch_id'      => $request->branch_id,
            'mandatory_only' => $request->boolean('mandatory_only'),
        ], $perPage);

        return $perPage === null ? $this->success($holidays) : $this->paginated($holidays);
    }

    /**
     * Create a public holiday.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'branch_id' => ['nullable', $this->branchOf($request)],
            'country_code' => 'nullable|string|max:3',
            'state_code' => 'nullable|string|max:10',
            'is_recurring' => 'nullable|boolean',
            'is_optional' => 'nullable|boolean',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        return $this->created($this->service->create($validated, $this->organizationId($request)));
    }

    /**
     * Show a public holiday.
     */
    public function show(PublicHoliday $publicHoliday): JsonResponse
    {
        return $this->success($publicHoliday->load('branch'));
    }

    /**
     * Update a public holiday.
     */
    public function update(Request $request, PublicHoliday $publicHoliday): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'holiday_date' => 'sometimes|date',
            'branch_id' => ['nullable', $this->branchOf($request)],
            'country_code' => 'nullable|string|max:3',
            'state_code' => 'nullable|string|max:10',
            'is_recurring' => 'nullable|boolean',
            'is_optional' => 'nullable|boolean',
            'year' => 'sometimes|integer|min:2000|max:2100',
        ]);

        return $this->success($this->service->update($publicHoliday, $validated));
    }

    /**
     * Delete a public holiday.
     */
    public function destroy(PublicHoliday $publicHoliday): JsonResponse
    {
        $this->service->delete($publicHoliday);

        return $this->success(null, 'Public holiday deleted successfully.');
    }

    /**
     * Bulk create holidays.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'holidays' => 'required|array|min:1',
            'holidays.*.name' => 'required|string|max:255',
            'holidays.*.holiday_date' => 'required|date',
            'holidays.*.branch_id' => ['nullable', $this->branchOf($request)],
            'holidays.*.country_code' => 'nullable|string|max:3',
            'holidays.*.state_code' => 'nullable|string|max:10',
            'holidays.*.is_recurring' => 'nullable|boolean',
            'holidays.*.is_optional' => 'nullable|boolean',
            'holidays.*.year' => 'required|integer|min:2000|max:2100',
        ]);

        return $this->created($this->service->createMany($validated['holidays'], $this->organizationId($request)));
    }

    /** A branch id that must belong to the caller's organization. */
    private function branchOf(Request $request): Exists
    {
        return Rule::exists('branches', 'id')->where('organization_id', $this->organizationId($request));
    }
}
