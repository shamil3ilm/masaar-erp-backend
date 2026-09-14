<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Resources\Sales\ContactResource;
use App\Models\Sales\Contact;
use App\Services\Sales\ContactService;
use App\Services\Sales\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private ContactService $contactService,
    ) {}

    /**
     * List contacts with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'contact_type' => $request->input('contact_type', $request->input('type')),
            'active_only' => $request->boolean('active_only'),
        ];

        if ($request->has('search')) {
            $filters['search'] = $request->input('search');
        }

        $contacts = $this->contactService->list($filters, $request->integer('per_page', 15));

        return $this->paginated($contacts, ContactResource::class);
    }

    /**
     * Create a new contact.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_type' => 'required|in:customer,supplier,both',
            'company_name' => 'nullable|string|max:200',
            'contact_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:100|unique:contacts,email,NULL,id,organization_id,' . $request->user()->organization_id,
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'tax_number' => 'nullable|string|max:50',
            'tax_registration_name' => 'nullable|string|max:200',
            'payment_terms' => 'nullable|integer|min:0|max:365',
            'credit_limit' => 'nullable|numeric|min:0',
            'currency_code' => 'nullable|string|size:3',
            'receivable_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('organization_id', $request->user()->organization_id)],
            'payable_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('organization_id', $request->user()->organization_id)],
            'billing_address_line_1' => 'nullable|string|max:255',
            'billing_address_line_2' => 'nullable|string|max:255',
            'billing_city' => 'nullable|string|max:100',
            'billing_state' => 'nullable|string|max:100',
            'billing_postal_code' => 'nullable|string|max:20',
            'billing_country_code' => 'nullable|string|size:2',
            'shipping_address_line_1' => 'nullable|string|max:255',
            'shipping_address_line_2' => 'nullable|string|max:255',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_state' => 'nullable|string|max:100',
            'shipping_postal_code' => 'nullable|string|max:20',
            'shipping_country_code' => 'nullable|string|size:2',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
        ]);

        $contact = $this->contactService->create($validated);

        return $this->created(new ContactResource($contact), 'Contact created successfully.');
    }

    /**
     * Show a contact.
     */
    public function show(Contact $contact): JsonResponse
    {
        $contact->load(['receivableAccount', 'payableAccount']);

        return $this->success(new ContactResource($contact));
    }

    /**
     * Update a contact.
     */
    public function update(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate([
            'contact_type' => 'sometimes|in:customer,supplier,both',
            'company_name' => 'nullable|string|max:200',
            'contact_name' => 'sometimes|required|string|max:100',
            'email' => 'nullable|email|max:100',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'tax_number' => 'nullable|string|max:50',
            'tax_registration_name' => 'nullable|string|max:200',
            'payment_terms' => 'nullable|integer|min:0|max:365',
            'credit_limit' => 'nullable|numeric|min:0',
            'currency_code' => 'nullable|string|size:3',
            'receivable_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('organization_id', $request->user()->organization_id)],
            'payable_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('organization_id', $request->user()->organization_id)],
            'billing_address_line_1' => 'nullable|string|max:255',
            'billing_address_line_2' => 'nullable|string|max:255',
            'billing_city' => 'nullable|string|max:100',
            'billing_state' => 'nullable|string|max:100',
            'billing_postal_code' => 'nullable|string|max:20',
            'billing_country_code' => 'nullable|string|size:2',
            'shipping_address_line_1' => 'nullable|string|max:255',
            'shipping_address_line_2' => 'nullable|string|max:255',
            'shipping_city' => 'nullable|string|max:100',
            'shipping_state' => 'nullable|string|max:100',
            'shipping_postal_code' => 'nullable|string|max:20',
            'shipping_country_code' => 'nullable|string|size:2',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'boolean',
        ]);

        $contact = $this->contactService->update($contact, $validated);

        return $this->success(new ContactResource($contact), 'Contact updated successfully.');
    }

    /**
     * Delete a contact.
     */
    public function destroy(Contact $contact): JsonResponse
    {
        return $this->tryAction(
            fn () => $this->contactService->delete($contact),
            'Contact deleted successfully.'
        );
    }

    /**
     * Get contact statement.
     */
    public function statement(Request $request, Contact $contact): JsonResponse
    {
        if (!$contact->isCustomer()) {
            return $this->success([
                'customer_id' => $contact->id,
                'period_start' => ($request->date('start_date') ?? now()->startOfYear())->format('Y-m-d'),
                'period_end' => ($request->date('end_date') ?? now())->format('Y-m-d'),
                'opening_balance' => 0,
                'closing_balance' => 0,
                'total_invoiced' => 0,
                'total_paid' => 0,
                'lines' => [],
            ], 'Statement retrieved successfully.');
        }

        $statement = $this->paymentService->getCustomerStatement(
            $contact->id,
            $request->date('start_date'),
            $request->date('end_date')
        );

        return $this->success($statement, 'Statement retrieved successfully.');
    }

    /**
     * Get contact balance summary.
     */
    public function balance(Contact $contact): JsonResponse
    {
        return $this->success([
            'outstanding_balance' => $contact->getOutstandingBalance(),
            'credit_limit' => $contact->credit_limit,
            'available_credit' => $contact->getAvailableCredit(),
            'is_over_limit' => $contact->isOverCreditLimit(),
        ], 'Balance retrieved successfully.');
    }

    /**
     * Block or unblock a contact for payment processing (SAP FI-AP).
     * PATCH /contacts/{contact}/payment-block  {"blocked": true, "reason": "..."}
     *
     * The contact is returned with its fields as stored, less the tax number,
     * which leaves only masked through ContactResource.
     */
    public function setPaymentBlock(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate([
            'blocked' => ['required', 'boolean'],
            'reason'  => ['required_if:blocked,true', 'nullable', 'string', 'max:500'],
        ]);

        $contact = $this->contactService->setPaymentBlock(
            $contact,
            (bool) $validated['blocked'],
            $validated['reason'] ?? null
        );

        return $this->success(
            $contact->makeHidden('tax_number'),
            $validated['blocked'] ? 'Contact payment blocked.' : 'Contact payment unblocked.'
        );
    }
}
