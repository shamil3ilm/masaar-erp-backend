<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Accounting\XbrlFiling;
use App\Models\Accounting\XbrlTaxonomy;
use App\Services\Accounting\XbrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class XbrlController extends Controller
{
    use ValidatesOwnedRows;

    public function __construct(
        private XbrlService $xbrlService
    ) {}

    // =========================================================================
    // Taxonomies
    // =========================================================================

    public function taxonomiesIndex(Request $request): JsonResponse
    {
        $taxonomies = $this->xbrlService->paginateTaxonomies(
            $request->boolean('active_only'),
            $request->integer('per_page', 50),
        );

        return $this->paginated($taxonomies);
    }

    public function taxonomiesStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'version'         => ['required', 'string', 'max:20'],
            'namespace'       => ['required', 'string', 'url', 'max:500'],
            'schema_location' => ['nullable', 'string', 'max:500'],
            'description'     => ['nullable', 'string'],
        ]);

        try {
            $taxonomy = $this->xbrlService->createTaxonomy(
                $this->organizationId($request),
                $validated,
                auth()->id()
            );

            return $this->created($taxonomy, 'Taxonomy created successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'DUPLICATE_NAMESPACE', 422);
        }
    }

    public function taxonomiesShow(XbrlTaxonomy $xbrlTaxonomy): JsonResponse
    {
        return $this->success($xbrlTaxonomy);
    }

    public function taxonomiesUpdate(Request $request, XbrlTaxonomy $xbrlTaxonomy): JsonResponse
    {
        $validated = $request->validate([
            'name'            => ['sometimes', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'schema_location' => ['nullable', 'string', 'max:500'],
            'is_active'       => ['sometimes', 'boolean'],
        ]);

        $taxonomy = $this->xbrlService->updateTaxonomy($xbrlTaxonomy, $validated);

        return $this->success($taxonomy, 'Taxonomy updated.');
    }

    // =========================================================================
    // Filings
    // =========================================================================

    public function filingsIndex(Request $request): JsonResponse
    {
        $filings = $this->xbrlService->paginateFilings(
            $request->status,
            $request->fiscal_year_id,
            $request->integer('per_page', 20),
        );

        return $this->paginated($filings);
    }

    public function filingsStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year_id'         => ['required', $this->ownedBy('fiscal_years')],
            'taxonomy_id'            => ['required', $this->ownedBy('xbrl_taxonomies')],
            'report_type'            => ['nullable', 'in:annual,semi_annual,quarterly,interim'],
            'period_start'           => ['nullable', 'date'],
            'period_end'             => ['nullable', 'date'],
            'seed_from_trial_balance' => ['nullable', 'boolean'],
        ]);

        $filing = $this->xbrlService->createFilingFromIds(
            $this->organizationId($request),
            $validated['fiscal_year_id'],
            $validated['taxonomy_id'],
            $validated,
            auth()->id()
        );

        return $this->created($filing, 'Filing created successfully.');
    }

    public function filingsShow(XbrlFiling $xbrlFiling): JsonResponse
    {
        $xbrlFiling->load(['taxonomy', 'fiscalYear', 'elements', 'createdBy:id,name']);

        return $this->success($xbrlFiling);
    }

    /**
     * Add or update a tagged element in a draft filing.
     */
    public function upsertElement(Request $request, XbrlFiling $xbrlFiling): JsonResponse
    {
        $validated = $request->validate([
            'concept'      => ['required', 'string', 'max:255'],
            'context_ref'  => ['required', 'string', 'max:255'],
            'unit_ref'     => ['nullable', 'string', 'max:50'],
            'value'        => ['required', 'string', 'max:1000'],
            'decimals'     => ['nullable', 'integer'],
            'period_type'  => ['nullable', 'in:instant,duration'],
            'balance_type' => ['nullable', 'in:debit,credit'],
            'sequence'     => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $element = $this->xbrlService->upsertElement($xbrlFiling, $validated);

            return $this->success($element, 'Element saved.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ELEMENT_FAILED', 422);
        }
    }

    /**
     * Validate a draft filing and promote to 'validated' status on success.
     */
    public function validate(XbrlFiling $xbrlFiling): JsonResponse
    {
        try {
            $filing = $this->xbrlService->validate($xbrlFiling);

            $hasErrors = ! empty($filing->validation_errors);

            return $this->success(
                ['filing' => $filing, 'errors' => $filing->validation_errors ?? []],
                $hasErrors ? 'Filing has validation errors.' : 'Filing validated successfully.'
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_FAILED', 400);
        }
    }

    /**
     * Generate iXBRL XML for a validated filing.
     */
    public function generateXml(XbrlFiling $xbrlFiling): JsonResponse
    {
        try {
            $filing = $this->xbrlService->generateXml($xbrlFiling);

            return $this->success(['filing_id' => $filing->id], 'XML generated successfully.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'GENERATION_FAILED', 422);
        }
    }

    /**
     * Download the generated iXBRL XML as a file.
     */
    public function downloadXml(XbrlFiling $xbrlFiling): Response|JsonResponse
    {
        if (empty($xbrlFiling->xml_content)) {
            return $this->error('No XML has been generated for this filing yet.', 'NO_XML', 404);
        }

        $filename = "xbrl-filing-{$xbrlFiling->uuid}-{$xbrlFiling->period_end->format('Y-m-d')}.xhtml";

        return response($xbrlFiling->xml_content, 200, [
            'Content-Type'        => 'application/xhtml+xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Mark a validated filing as submitted to the regulatory authority.
     */
    public function submit(Request $request, XbrlFiling $xbrlFiling): JsonResponse
    {
        $validated = $request->validate([
            'external_reference' => ['required', 'string', 'max:255'],
        ]);

        try {
            $filing = $this->xbrlService->markSubmitted($xbrlFiling, $validated['external_reference']);

            return $this->success($filing, 'Filing marked as submitted.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'SUBMIT_FAILED', 422);
        }
    }

    /**
     * Mark a filing as accepted or rejected by the regulatory authority.
     * POST /xbrl/filings/{id}/review  {"action": "accept"|"reject", "errors": [...]}
     */
    public function review(Request $request, XbrlFiling $xbrlFiling): JsonResponse
    {
        $validated = $request->validate([
            'action'   => ['required', 'in:accept,reject'],
            'errors'   => ['required_if:action,reject', 'array'],
            'errors.*' => ['string'],
        ]);

        try {
            if ($validated['action'] === 'accept') {
                $filing = $this->xbrlService->markAccepted($xbrlFiling);
                return $this->success($filing, 'Filing accepted.');
            }

            $filing = $this->xbrlService->markRejected($xbrlFiling, $validated['errors'] ?? []);
            return $this->success($filing, 'Filing marked as rejected.');
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'ACTION_FAILED', 422);
        }
    }
}
