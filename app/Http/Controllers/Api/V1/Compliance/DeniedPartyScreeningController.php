<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Services\Compliance\DeniedPartyScreeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeniedPartyScreeningController extends Controller
{
    use ReportsBusinessRules;
    use ValidatesOwnedRows;

    public function __construct(
        private readonly DeniedPartyScreeningService $dpsService
    ) {}

    // -------------------------------------------------------------------------
    // Sanction Lists
    // -------------------------------------------------------------------------

    public function lists(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->dpsService->paginateLists($this->organizationId($request), $request->integer('per_page', 15))
        );
    }

    public function storeList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'list_name'      => 'required|string|max:100',
            'list_authority' => 'required|in:OFAC,EU,UN,HMT,local,other',
            'list_type'      => 'required|in:denied_party,embargo,debarred',
            'is_active'      => 'nullable|boolean',
            'auto_sync'      => 'nullable|boolean',
            'sync_url'       => 'nullable|url|max:255',
        ]);

        return $this->created($this->dpsService->createList($validated, $this->organizationId($request)));
    }

    public function showList(int $id): JsonResponse
    {
        return $this->success($this->dpsService->findListWithActiveEntryCount($id));
    }

    public function updateList(Request $request, int $id): JsonResponse
    {
        $list      = $this->dpsService->findList($id);
        $validated = $request->validate([
            'list_name'      => 'sometimes|string|max:100',
            'list_authority' => 'sometimes|in:OFAC,EU,UN,HMT,local,other',
            'list_type'      => 'sometimes|in:denied_party,embargo,debarred',
            'is_active'      => 'nullable|boolean',
            'auto_sync'      => 'nullable|boolean',
            'sync_url'       => 'nullable|url|max:255',
        ]);

        return $this->success($this->dpsService->updateList($list, $validated));
    }

    // -------------------------------------------------------------------------
    // List Entries
    // -------------------------------------------------------------------------

    public function listEntries(Request $request, int $listId): JsonResponse
    {
        $list = $this->dpsService->findList($listId);

        return $this->paginated(
            $this->dpsService->paginateEntries($list, $request->input('search'), $request->integer('per_page', 20))
        );
    }

    public function storeEntry(Request $request, int $listId): JsonResponse
    {
        $list = $this->dpsService->findList($listId);

        $validated = $request->validate([
            'entry_type'     => 'required|in:person,entity,vessel,aircraft',
            'name'           => 'required|string|max:200',
            'aliases'        => 'nullable|array',
            'aliases.*'      => 'string|max:200',
            'country_code'   => 'nullable|string|size:3',
            'address'        => 'nullable|string|max:300',
            'id_number'      => 'nullable|string|max:100',
            'program'        => 'nullable|string|max:100',
            'remarks'        => 'nullable|string',
            'effective_date' => 'nullable|date',
            'expiry_date'    => 'nullable|date|after_or_equal:effective_date',
            'is_active'      => 'nullable|boolean',
        ]);

        return $this->created($this->dpsService->addEntry($list, $validated));
    }

    public function importEntries(Request $request, int $listId): JsonResponse
    {
        $list = $this->dpsService->findList($listId);

        $validated = $request->validate([
            'entries'                => 'required|array|min:1|max:5000',
            'entries.*.entry_type'   => 'nullable|in:person,entity,vessel,aircraft',
            'entries.*.name'         => 'required|string|max:200',
            'entries.*.aliases'      => 'nullable|array',
            'entries.*.country_code' => 'nullable|string|max:3',
            'entries.*.id_number'    => 'nullable|string|max:100',
        ]);

        $count = $this->dpsService->importListEntries($list->id, $validated['entries']);

        return $this->success(['imported' => $count], "{$count} entries imported.");
    }

    // -------------------------------------------------------------------------
    // Screening
    // -------------------------------------------------------------------------

    public function screenContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', $this->ownedBy('contacts')],
            'threshold'  => 'nullable|numeric|min:0|max:100',
        ]);

        $run = $this->dpsService->screenContact(
            $validated['contact_id'],
            (float) ($validated['threshold'] ?? 80.0)
        );

        return $this->success($run->load('results.listEntry'));
    }

    public function screenAll(Request $request): JsonResponse
    {
        $summary = $this->dpsService->screenAll($this->organizationId($request));

        return $this->success($summary, 'Bulk screening complete.');
    }

    // -------------------------------------------------------------------------
    // Screening Runs
    // -------------------------------------------------------------------------

    public function runs(Request $request): JsonResponse
    {
        return $this->paginated($this->dpsService->paginateRuns(
            $this->organizationId($request),
            ['status' => $request->input('status'), 'entity_type' => $request->input('entity_type')],
            $request->integer('per_page', 15),
        ));
    }

    public function showRun(int $id): JsonResponse
    {
        return $this->success($this->dpsService->findRunWithDetails($id));
    }

    public function clearRun(Request $request, int $id): JsonResponse
    {
        $run       = $this->dpsService->findRun($id);
        $validated = $request->validate([
            'notes' => 'required|string|min:5|max:1000',
        ]);

        try {
            $run = $this->dpsService->clearScreening($run, auth()->id(), $validated['notes']);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($run, 'Screening run cleared.');
    }

    // -------------------------------------------------------------------------
    // Pending Reviews & Status
    // -------------------------------------------------------------------------

    public function pendingReviews(Request $request): JsonResponse
    {
        return $this->success($this->dpsService->getPendingReviews($this->organizationId($request)));
    }

    public function checkContact(int $contactId): JsonResponse
    {
        $latestRun = $this->dpsService->latestRunForContact($contactId);

        return $this->success([
            'contact_id'          => $contactId,
            'is_clean'            => $this->dpsService->isContactClean($contactId),
            'latest_run_status'   => $latestRun?->status,
            'last_screened_at'    => $latestRun?->screening_date,
        ]);
    }
}
