<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\PeriodLockService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeriodLockController extends Controller
{
    public function __construct(private readonly PeriodLockService $periodLockService) {}

    /**
     * List all active period lock overrides for the authenticated organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        $overrides = $this->periodLockService->getActiveOverrides($organizationId);

        return $this->success($overrides);
    }

    /**
     * Grant a period lock override to a user.
     * Requires permission: accounting.period-lock.manage
     */
    public function store(Request $request): JsonResponse
    {
        $organizationId = $this->organizationId($request);

        // The period and the user must both belong to this organization: the
        // response echoes the user's name and email and the period's dates.
        // Periods have no organization column, so they are matched through
        // their fiscal year.
        $validated = $request->validate([
            'period_id' => [
                'required',
                'integer',
                Rule::exists('accounting_periods', 'id')->where(
                    fn (Builder $query) => $query->whereIn(
                        'fiscal_year_id',
                        fn (Builder $years) => $years->select('id')
                            ->from('fiscal_years')
                            ->where('organization_id', $organizationId)
                    )
                ),
            ],
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
            ],
            'reason' => ['required', 'string', 'max:1000'],
            'valid_until' => ['nullable', 'date', 'after:now'],
        ]);

        $grantedBy = auth()->id();

        $override = $this->periodLockService->grantOverride(
            organizationId: $organizationId,
            periodId: (int) $validated['period_id'],
            userId: (int) $validated['user_id'],
            grantedBy: $grantedBy,
            validUntil: isset($validated['valid_until']) ? Carbon::parse($validated['valid_until']) : null,
            reason: $validated['reason'],
        );

        $override->load(['user:id,name,email', 'grantedByUser:id,name', 'period']);

        return $this->created($override, 'Period lock override granted successfully.');
    }

    /**
     * Revoke an existing override.
     * Requires permission: accounting.period-lock.manage
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $this->periodLockService->revokeOverride($id, auth()->id());

        return $this->success(null, 'Period lock override revoked.');
    }

    /**
     * Check whether a date is within a locked period.
     * GET /check?date=YYYY-MM-DD
     */
    public function checkPeriod(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $date = $request->query('date');
        $organizationId = $this->organizationId($request);
        $userId = auth()->id();

        $locked = $this->periodLockService->isLockedForUser($organizationId, $date, $userId);

        $period = $this->periodLockService->periodSummaryForDate($organizationId, $date);

        return $this->success([
            'locked' => $locked,
            'period' => $period,
        ]);
    }
}
