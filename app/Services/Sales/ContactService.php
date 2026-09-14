<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Jobs\RunAmlScreeningJob;
use App\Models\Sales\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

/**
 * Lists, creates, edits and deletes customer and supplier contacts.
 */
class ContactService
{
    /**
     * Contacts of the current organization, newest first.
     *
     * contact_type narrows to customers or suppliers when it is one of those;
     * search applies whenever its key is present; active_only keeps active
     * contacts when true.
     *
     * @param  array{contact_type?: ?string, search?: ?string, active_only?: bool}  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Contact::query();

        match ($filters['contact_type'] ?? null) {
            'customer' => $query->customers(),
            'supplier' => $query->suppliers(),
            default => null,
        };

        return $query
            ->when(array_key_exists('search', $filters), fn ($q) => $q->search((string) $filters['search']))
            ->when($filters['active_only'] ?? false, fn ($q) => $q->active())
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Create a contact and queue its AML screening.
     *
     * A contact without a contact name takes its company name. The screening
     * runs in the background; a failure to queue it is logged and does not
     * undo the contact.
     *
     * @param  array<string, mixed>  $data  validated contact fields
     */
    public function create(array $data): Contact
    {
        if (empty($data['contact_name'])) {
            $data['contact_name'] = $data['company_name'] ?? 'N/A';
        }

        $contact = Contact::create($data);

        try {
            RunAmlScreeningJob::dispatch($contact->id, $contact->organization_id);
        } catch (\Throwable $e) {
            Log::warning('AML screening dispatch failed for new contact', ['contact_id' => $contact->id, 'error' => $e->getMessage()]);
        }

        return $contact;
    }

    /**
     * @param  array<string, mixed>  $data  validated contact fields
     */
    public function update(Contact $contact, array $data): Contact
    {
        $contact->update($data);

        return $contact->fresh();
    }

    /**
     * Delete a contact that no invoice refers to.
     *
     * @throws \InvalidArgumentException when the contact has invoices
     */
    public function delete(Contact $contact): void
    {
        if ($contact->invoices()->count() > 0) {
            throw new \InvalidArgumentException('Cannot delete contact with existing invoices.');
        }

        $contact->delete();
    }

    /**
     * Block a contact for payment processing with a reason, or lift the block.
     */
    public function setPaymentBlock(Contact $contact, bool $blocked, ?string $reason): Contact
    {
        $contact->update([
            'payment_block' => $blocked,
            'payment_block_reason' => $blocked ? $reason : null,
        ]);

        return $contact->fresh();
    }
}
