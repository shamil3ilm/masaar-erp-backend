<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Accounting\FinancialCloseCockpitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinancialCloseCockpitController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private readonly FinancialCloseCockpitService $service
    ) {}

    /**
     * List close templates for the organization.
     */
    public function templates(Request $request): JsonResponse
    {
        $templates = $this->service->listTemplates($request->boolean('active_only'));

        return $this->success($templates);
    }

    /**
     * Create a new close template.
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'close_type'  => ['required', Rule::in(['month_end', 'quarter_end', 'year_end'])],
            'is_active'   => ['boolean'],
            'tasks'       => ['nullable', 'array'],
            'tasks.*.task_name'               => ['required_with:tasks', 'string', 'max:255'],
            'tasks.*.description'             => ['nullable', 'string'],
            'tasks.*.task_type'               => ['required_with:tasks', Rule::in(['journal', 'reconciliation', 'report', 'approval', 'manual'])],
            'tasks.*.sort_order'              => ['nullable', 'integer'],
            'tasks.*.estimated_duration_hours' => ['nullable', 'numeric', 'min:0'],
            'tasks.*.required_role'           => ['nullable', 'string', 'max:100'],
        ]);

        $template = $this->service->createTemplate(auth()->user()->organization_id, $validated);

        return $this->created($template);
    }

    /**
     * List close periods for the organization.
     */
    public function periods(Request $request): JsonResponse
    {
        $periods = $this->service->paginatePeriods(
            $request->input('fiscal_year'),
            $request->input('status'),
            $request->integer('per_page', 25),
        );

        return $this->paginated($periods);
    }

    /**
     * Create a new close period.
     */
    public function storePeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'financial_close_template_id' => ['nullable', 'integer', $this->ownedBy('financial_close_templates')],
            'fiscal_year'                 => ['required', 'integer'],
            'period'                      => ['required', 'integer', 'min:1', 'max:12'],
            'close_type'                  => ['required', Rule::in(['month_end', 'quarter_end', 'year_end'])],
            'due_date'                    => ['nullable', 'date'],
        ]);

        $validated['organization_id'] = auth()->user()->organization_id;

        $period = $this->service->createPeriod($validated);

        return $this->created($period->load('tasks'));
    }

    /**
     * Show a close period with its tasks.
     */
    public function showPeriod(int $id): JsonResponse
    {
        $period = $this->service->findPeriod($id, ['tasks', 'tasks.assignedTo:id,name', 'tasks.completedBy:id,name']);

        return $this->success($period);
    }

    /**
     * Start a close task.
     */
    public function startTask(Request $request, int $taskId): JsonResponse
    {
        $task = $this->service->findTask($taskId);
        $this->service->startTask($task, auth()->id());

        return $this->success($task->fresh(), 'Task started.');
    }

    /**
     * Complete a close task.
     */
    public function completeTask(Request $request, int $taskId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $task = $this->service->findTask($taskId);
        $this->service->completeTask($task, auth()->id(), $validated['notes'] ?? '');

        return $this->success($task->fresh(), 'Task completed.');
    }

    /**
     * Close a financial period.
     */
    public function closePeriod(int $id): JsonResponse
    {
        $period = $this->service->findPeriod($id);
        $this->service->closePeriod($period, auth()->id());

        return $this->success($period->fresh(), 'Period closed successfully.');
    }

    /**
     * Get progress summary for a period.
     */
    public function progress(int $id): JsonResponse
    {
        $period   = $this->service->findPeriod($id);
        $progress = $this->service->getPeriodProgress($period);

        return $this->success($progress);
    }

    /**
     * Skip a close task (with mandatory reason).
     */
    public function skipTask(Request $request, int $taskId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $task = $this->service->findTask($taskId);

        return $this->tryAction(
            function () use ($task, $validated) {
                $this->service->skipTask($task, auth()->id(), $validated['reason']);
                return $task->fresh();
            },
            'Task skipped.',
            'INVALID_TRANSITION'
        );
    }

    /**
     * Assign a close task to a user.
     */
    public function assignTask(Request $request, int $taskId): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', $this->ownedBy('users')],
        ]);

        $task = $this->service->findTask($taskId);

        return $this->tryAction(
            function () use ($task, $validated) {
                $this->service->assignTask($task, (int) $validated['assigned_to']);
                return $task->fresh(['assignedTo:id,name']);
            },
            'Task assigned.',
            'ASSIGN_FAILED'
        );
    }

    /**
     * Sign off on a closed period (CFO / controller approval).
     */
    public function signOff(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $period = $this->service->findPeriod($id);

        return $this->tryAction(
            function () use ($period, $validated) {
                $this->service->signOff($period, auth()->id(), $validated['notes'] ?? '');
                return $period->fresh();
            },
            'Period signed off successfully.',
            'SIGNOFF_FAILED'
        );
    }
}
