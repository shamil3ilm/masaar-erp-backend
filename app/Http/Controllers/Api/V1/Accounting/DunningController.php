<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\DunningBlock;
use App\Models\Accounting\DunningLevel;
use App\Models\Accounting\DunningRun;
use App\Models\Sales\Contact;
use App\Services\Accounting\DunningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DunningController extends Controller
{
    public function __construct(
        private readonly DunningService $dunningService
    ) {}

    // -------------------------------------------------------------------------
    // Dunning Levels
    // -------------------------------------------------------------------------

    public function indexLevels(Request $request): JsonResponse
    {
        $levels = $this->dunningService->listLevels($this->organizationId($request));

        return $this->success($levels, 'Dunning levels retrieved.');
    }

    public function storeLevels(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'level_number'      => 'required|integer|min:1|max:9',
            'name'              => 'required|string|max:100',
            'days_overdue_from' => 'required|integer|min:1',
            'days_overdue_to'   => 'nullable|integer|min:1|gt:days_overdue_from',
            'interest_rate'     => 'nullable|numeric|min:0|max:999.99',
            'dunning_fee'       => 'nullable|numeric|min:0',
            'is_legal_action'   => 'nullable|boolean',
            'is_active'         => 'nullable|boolean',
        ]);

        $level = $this->dunningService->createLevel($this->organizationId($request), $validated);

        return $this->success($level, 'Dunning level created.', 201);
    }

    public function updateLevel(Request $request, DunningLevel $dunningLevel): JsonResponse
    {
        $validated = $request->validate([
            'name'              => 'sometimes|string|max:100',
            'days_overdue_from' => 'sometimes|integer|min:1',
            'days_overdue_to'   => 'nullable|integer|min:1',
            'interest_rate'     => 'nullable|numeric|min:0|max:999.99',
            'dunning_fee'       => 'nullable|numeric|min:0',
            'is_legal_action'   => 'nullable|boolean',
            'is_active'         => 'nullable|boolean',
        ]);

        $dunningLevel->update($validated);

        return $this->success($dunningLevel->fresh(), 'Dunning level updated.');
    }

    public function destroyLevel(DunningLevel $dunningLevel): JsonResponse
    {
        $dunningLevel->delete();

        return $this->success(null, 'Dunning level deleted.');
    }

    // -------------------------------------------------------------------------
    // Dunning Runs
    // -------------------------------------------------------------------------

    public function runDunning(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'run_date' => 'required|date',
        ]);

        $organization = $this->organization($request);
        $run          = $this->dunningService->runDunning(
            $organization,
            Carbon::parse($validated['run_date'])
        );

        return $this->success($run->load(['notices.contact', 'notices.dunningLevel']), 'Dunning run created.', 201);
    }

    public function indexRuns(Request $request): JsonResponse
    {
        $runs = $this->dunningService->listRuns(
            $this->organizationId($request),
            $request->integer('per_page', 15),
        );

        return $this->paginated($runs, null, 'Dunning runs retrieved.');
    }

    public function showRun(DunningRun $dunningRun): JsonResponse
    {
        return $this->success(
            $dunningRun->load(['notices.contact', 'notices.dunningLevel', 'notices.items']),
            'Dunning run retrieved.'
        );
    }

    public function postRun(DunningRun $dunningRun): JsonResponse
    {
        $run = $this->dunningService->postRun($dunningRun);

        return $this->success($run, 'Dunning run posted.');
    }

    public function sendNotices(DunningRun $dunningRun): JsonResponse
    {
        $count = $this->dunningService->sendNotices($dunningRun);

        return $this->success(['sent_count' => $count], "Sent {$count} dunning notices.");
    }

    // -------------------------------------------------------------------------
    // Dunning Blocks
    // -------------------------------------------------------------------------

    public function indexBlocks(Request $request): JsonResponse
    {
        $blocks = $this->dunningService->listBlocks(
            $this->organizationId($request),
            $request->boolean('active_only', false),
            $request->integer('per_page', 15),
        );

        return $this->paginated($blocks, null, 'Dunning blocks retrieved.');
    }

    public function createBlock(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate([
            'reason'        => 'required|string|max:500',
            'blocked_until' => 'nullable|date|after:today',
        ]);

        $block = $this->dunningService->blockCustomer($contact, $validated);

        return $this->success($block->load(['contact', 'blockedBy']), 'Dunning block created.', 201);
    }

    public function releaseBlock(Request $request, DunningBlock $dunningBlock): JsonResponse
    {
        $validated = $request->validate([
            'release_reason' => 'required|string|max:500',
        ]);

        $block = $this->dunningService->releaseBlock($dunningBlock, $validated['release_reason']);

        return $this->success($block->load(['contact', 'releasedBy']), 'Dunning block released.');
    }
}
