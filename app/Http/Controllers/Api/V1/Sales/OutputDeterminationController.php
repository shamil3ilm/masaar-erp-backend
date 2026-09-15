<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Exceptions\ERP\BusinessRuleException;
use App\Http\Concerns\ReportsBusinessRules;
use App\Http\Concerns\ValidatesOwnedRows;
use App\Http\Controllers\Controller;
use App\Models\Sales\OutputMessage;
use App\Models\Sales\OutputType;
use App\Services\Sales\OutputDeterminationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutputDeterminationController extends Controller
{
    use ReportsBusinessRules, ValidatesOwnedRows;

    public function __construct(
        private OutputDeterminationService $outputDeterminationService
    ) {}

    // ─────────────────────────────────────────────────────────────
    // Output Types CRUD
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/sales/output-determination/output-types
     */
    public function index(Request $request): JsonResponse
    {
        $types = $this->outputDeterminationService->listTypes(
            $request->has('document_type') ? (string) $request->string('document_type') : null,
            $request->has('active_only'),
            $request->integer('per_page', 15)
        );

        return $this->paginated($types);
    }

    /**
     * POST /api/v1/sales/output-determination/output-types
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'            => 'required|string|max:10',
            'name'            => 'required|string|max:100',
            'document_type'   => 'required|in:invoice,sales_order,quotation,delivery_note,purchase_order,payment',
            'output_medium'   => 'required|in:print,email,edi,portal',
            'email_template'  => 'nullable|string|max:100',
            'print_template'  => 'nullable|string|max:100',
            'dispatch_time'   => 'required|in:immediately,on_save,on_post,scheduled',
            'is_active'       => 'nullable|boolean',
            'condition_records'                      => 'nullable|array',
            'condition_records.*.key_combination'    => 'required|in:customer,customer_group,all',
            'condition_records.*.customer_id'        => ['nullable', 'integer', $this->ownedBy('contacts')],
            'condition_records.*.customer_group_id'  => ['nullable', 'integer', $this->ownedBy('customer_groups')],
            'condition_records.*.valid_from'         => 'nullable|date',
            'condition_records.*.valid_to'           => 'nullable|date',
        ]);

        $conditionRecords = $validated['condition_records'] ?? [];
        unset($validated['condition_records']);

        $outputType = $this->outputDeterminationService->createType(
            (int) $this->organizationId($request),
            $validated,
            $conditionRecords
        );

        return $this->success($outputType, 'Output type created.', 201);
    }

    /**
     * GET /api/v1/sales/output-determination/output-types/{outputType}
     */
    public function show(OutputType $outputType): JsonResponse
    {
        return $this->success($this->outputDeterminationService->typeDetails($outputType));
    }

    /**
     * PUT /api/v1/sales/output-determination/output-types/{outputType}
     */
    public function update(Request $request, OutputType $outputType): JsonResponse
    {
        $validated = $request->validate([
            'code'           => 'sometimes|string|max:10',
            'name'           => 'sometimes|string|max:100',
            'document_type'  => 'sometimes|in:invoice,sales_order,quotation,delivery_note,purchase_order,payment',
            'output_medium'  => 'sometimes|in:print,email,edi,portal',
            'email_template' => 'nullable|string|max:100',
            'print_template' => 'nullable|string|max:100',
            'dispatch_time'  => 'sometimes|in:immediately,on_save,on_post,scheduled',
            'is_active'      => 'nullable|boolean',
        ]);

        return $this->success(
            $this->outputDeterminationService->updateType($outputType, $validated),
            'Output type updated.'
        );
    }

    /**
     * DELETE /api/v1/sales/output-determination/output-types/{outputType}
     */
    public function destroy(OutputType $outputType): JsonResponse
    {
        $this->outputDeterminationService->deleteType($outputType);

        return $this->success(null, 'Output type deleted.');
    }

    // ─────────────────────────────────────────────────────────────
    // Output Messages
    // ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/sales/output-determination/messages
     */
    public function messages(Request $request): JsonResponse
    {
        $messages = $this->outputDeterminationService->listMessages([
            'status'        => $request->has('status') ? (string) $request->string('status') : null,
            'document_type' => $request->has('document_type') ? (string) $request->string('document_type') : null,
            'document_id'   => $request->has('document_id') ? $request->integer('document_id') : null,
            'medium'        => $request->has('medium') ? (string) $request->string('medium') : null,
        ], $request->integer('per_page', 15));

        return $this->paginated($messages);
    }

    /**
     * POST /api/v1/sales/output-determination/messages/{outputMessage}/retry
     */
    public function retryMessage(OutputMessage $outputMessage): JsonResponse
    {
        try {
            $message = $this->outputDeterminationService->retry($outputMessage);
        } catch (BusinessRuleException $e) {
            return $this->ruleError($e);
        }

        return $this->success($message, 'Output message dispatched.');
    }
}
