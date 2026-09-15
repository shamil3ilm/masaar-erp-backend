<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ScrapReport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Scrap reports: recording, correction and GL posting.
 *
 * A report posted to the GL is final. Update, delete and posting run on the
 * locked report and check the posted flag there, so a stale copy cannot post
 * twice or change a report that was posted meanwhile.
 */
class ScrapReportingService
{
    public function list(array $filters = []): Collection
    {
        return ScrapReport::with(['product', 'workOrder', 'warehouse', 'reportedBy'])
            ->when(isset($filters['work_order_id']), fn($q) => $q->forWorkOrder((int) $filters['work_order_id']))
            ->when(isset($filters['product_id']), fn($q) => $q->forProduct((int) $filters['product_id']))
            ->when(isset($filters['scrap_cause']), fn($q) => $q->where('scrap_cause', $filters['scrap_cause']))
            ->when(isset($filters['gl_posted']), fn($q) => $q->where('gl_posted', (bool) $filters['gl_posted']))
            ->when(isset($filters['from_date']), fn($q) => $q->where('scrap_date', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn($q) => $q->where('scrap_date', '<=', $filters['to_date']))
            ->orderByDesc('scrap_date')
            ->get();
    }

    /**
     * One of the organization's scrap reports.
     *
     * @param  list<string>  $with
     */
    public function findOrFail(int $id, array $with = []): ScrapReport
    {
        return ScrapReport::with($with)->findOrFail($id);
    }

    public function create(array $data): ScrapReport
    {
        return ScrapReport::create($data);
    }

    public function update(ScrapReport $report, array $data): ScrapReport
    {
        return $report->lockForTransition(function (ScrapReport $report) use ($data): ScrapReport {
            if ($report->gl_posted) {
                throw ValidationException::withMessages([
                    'gl_posted' => 'Cannot update a scrap report that has already been posted to GL.',
                ]);
            }

            $report->update($data);

            return $report->fresh();
        });
    }

    /**
     * Delete a report that has not been posted to the GL.
     */
    public function delete(ScrapReport $report): void
    {
        $report->lockForTransition(function (ScrapReport $report): void {
            if ($report->gl_posted) {
                throw new \InvalidArgumentException('Cannot delete a scrap report that has been posted to GL.');
            }

            $report->delete();
        });
    }

    public function postToGL(ScrapReport $report): ScrapReport
    {
        return $report->lockForTransition(function (ScrapReport $report): ScrapReport {
            if ($report->gl_posted) {
                throw ValidationException::withMessages([
                    'gl_posted' => 'This scrap report has already been posted to GL.',
                ]);
            }

            $report->markGlPosted();

            return $report->fresh();
        });
    }

    public function getScrapSummary(array $filters): array
    {
        $query = ScrapReport::query()
            ->when(isset($filters['from_date']), fn($q) => $q->where('scrap_date', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn($q) => $q->where('scrap_date', '<=', $filters['to_date']))
            ->when(isset($filters['work_order_id']), fn($q) => $q->forWorkOrder((int) $filters['work_order_id']));

        $byProduct = (clone $query)
            ->with('product:id,name')
            ->selectRaw('product_id, SUM(scrap_quantity) as total_quantity, SUM(estimated_value) as total_value, SUM(recovery_value) as total_recovery, COUNT(*) as report_count')
            ->groupBy('product_id')
            ->get();

        $byCause = (clone $query)
            ->selectRaw('scrap_cause, SUM(scrap_quantity) as total_quantity, SUM(estimated_value) as total_value, COUNT(*) as report_count')
            ->groupBy('scrap_cause')
            ->get();

        $byWorkOrder = (clone $query)
            ->whereNotNull('work_order_id')
            ->with('workOrder:id,work_order_number')
            ->selectRaw('work_order_id, SUM(scrap_quantity) as total_quantity, SUM(estimated_value) as total_value, COUNT(*) as report_count')
            ->groupBy('work_order_id')
            ->get();

        return [
            'by_product' => $byProduct,
            'by_cause' => $byCause,
            'by_work_order' => $byWorkOrder,
        ];
    }

    public function getTotalScrapValue(int $orgId, string $fromDate, string $toDate): float
    {
        return (float) ScrapReport::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereBetween('scrap_date', [$fromDate, $toDate])
            ->sum('estimated_value');
    }
}
