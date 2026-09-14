<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Models\Accounting\Account;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\DispatchesWebhooks;
use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Contact extends Model
{
    use BelongsToOrganization, DispatchesWebhooks, HasAuditTrail, HasFactory, HasUuid, Notifiable, SoftDeletes;

    /**
     * Route notifications for mail channel (contact email).
     * Contacts receive mail notifications only — no in-app (database) channel.
     */
    public function routeNotificationForMail(): string
    {
        return $this->email;
    }

    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_SUPPLIER = 'supplier';

    public const TYPE_BOTH = 'both';

    /**
     * Columns another document may embed to name its contact. They leave out
     * the tax number, email, phone and addresses, which leave only through
     * ContactResource.
     */
    public const REFERENCE_COLUMNS = ['id', 'uuid', 'contact_name', 'company_name'];

    protected $fillable = [
        'organization_id',
        'contact_type',
        'company_name',
        'contact_name',
        'email',
        'phone',
        'mobile',
        'website',
        'tax_number',
        'tax_registration_name',
        'payment_terms',
        'credit_limit',
        'currency_code',
        'receivable_account_id',
        'payable_account_id',
        'billing_address_line_1',
        'billing_address_line_2',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country_code',
        'shipping_address_line_1',
        'shipping_address_line_2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country_code',
        'notes',
        'is_active',
        'payment_block',
        'payment_block_reason',
        'created_by',
    ];

    protected $hidden = [
        'tax_number_hash',
    ];

    protected function casts(): array
    {
        return [
            'payment_terms' => 'integer',
            'credit_limit' => 'decimal:4',
            'is_active' => 'boolean',
            'payment_block' => 'boolean',
            'tax_number' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Contact $contact): void {
            if ($contact->isDirty('tax_number')) {
                $contact->tax_number_hash = static::taxNumberHash($contact->tax_number);
            }
        });
    }

    /**
     * Keyed hash of a tax number, for matching without decrypting.
     *
     * tax_number is encrypted with a fresh IV on every write, so neither an
     * equality nor a LIKE on it can ever match. Spaces, dashes and case are
     * dropped before hashing, so "300-000 000" and "300000000" are the same
     * number. Returns null when nothing is left to hash.
     */
    public static function taxNumberHash(?string $value): ?string
    {
        $normalised = strtoupper((string) preg_replace('/[\s\-]+/', '', (string) $value));

        return $normalised === '' ? null : hash_hmac('sha256', $normalised, (string) config('app.key'));
    }

    /**
     * Check if this contact is blocked for payment processing.
     */
    public function isPaymentBlocked(): bool
    {
        return (bool) $this->payment_block;
    }

    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payable_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'customer_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'customer_id');
    }

    public function paymentsReceived(): HasMany
    {
        return $this->hasMany(PaymentReceived::class, 'customer_id');
    }

    /**
     * Check if contact is a customer.
     */
    public function isCustomer(): bool
    {
        return in_array($this->contact_type, [self::TYPE_CUSTOMER, self::TYPE_BOTH], true);
    }

    /**
     * Check if contact is a supplier.
     */
    public function isSupplier(): bool
    {
        return in_array($this->contact_type, [self::TYPE_SUPPLIER, self::TYPE_BOTH], true);
    }

    /**
     * Get display name.
     */
    public function getDisplayName(): string
    {
        // Both are nullable, and the declared return type is not. A contact
        // carrying neither name made this a TypeError rather than an empty
        // label, which is how a lookup for a contact that does not exist
        // answered 500.
        return $this->company_name ?? $this->contact_name ?? '';
    }

    /**
     * Get full billing address.
     */
    public function getBillingAddress(): string
    {
        $parts = array_filter([
            $this->billing_address_line_1,
            $this->billing_address_line_2,
            $this->billing_city,
            $this->billing_state,
            $this->billing_postal_code,
            $this->billing_country_code,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get full shipping address.
     */
    public function getShippingAddress(): string
    {
        $parts = array_filter([
            $this->shipping_address_line_1,
            $this->shipping_address_line_2,
            $this->shipping_city,
            $this->shipping_state,
            $this->shipping_postal_code,
            $this->shipping_country_code,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Sum of amounts still due on this customer's open invoices.
     *
     * Derived from the invoices themselves — there is no stored balance column,
     * so callers never need to refresh it.
     */
    public function getOutstandingBalance(): float
    {
        return $this->invoices()
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->sum('amount_due');
    }

    /**
     * Check if customer is over credit limit.
     */
    public function isOverCreditLimit(): bool
    {
        if (! $this->credit_limit || $this->credit_limit <= 0) {
            return false;
        }

        return $this->getOutstandingBalance() >= $this->credit_limit;
    }

    /**
     * Get available credit.
     */
    public function getAvailableCredit(): float
    {
        if (! $this->credit_limit || $this->credit_limit <= 0) {
            return PHP_FLOAT_MAX;
        }

        return max(0, $this->credit_limit - $this->getOutstandingBalance());
    }

    public function scopeCustomers($query)
    {
        return $query->whereIn('contact_type', [self::TYPE_CUSTOMER, self::TYPE_BOTH]);
    }

    public function scopeSuppliers($query)
    {
        return $query->whereIn('contact_type', [self::TYPE_SUPPLIER, self::TYPE_BOTH]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('company_name', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                // Exact match only: the stored value is ciphertext. A term that
                // hashes to nothing must add no clause, or where(col, null)
                // becomes whereNull and matches every contact without one.
                ->when(static::taxNumberHash($term), fn ($q, $hash) => $q->orWhere('tax_number_hash', $hash));
        });
    }
}
