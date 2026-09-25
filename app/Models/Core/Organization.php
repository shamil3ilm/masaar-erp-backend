<?php

declare(strict_types=1);

namespace App\Models\Core;

use App\Models\Concerns\HasAuditTrail;
use App\Models\Concerns\HasUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory, SoftDeletes, HasUuid, HasAuditTrail;

    /**
     * parent_organization_id is left out on purpose: a request that fills an
     * organization from its own input must not be able to join a group. Only
     * App\Services\Admin\PlatformOrganizationService sets it.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'legal_name',
        'slug',
        'country_code',
        'tax_scheme',
        'tax_number',
        'base_currency',
        'fiscal_year_start_month',
        'fiscal_year_start_day',
        'email',
        'phone',
        'website',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'settings',
        'logo_url',
        'status',
        'is_active',
        'activated_at',
        'suspended_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'fiscal_year_start_month' => 'integer',
        'fiscal_year_start_day' => 'integer',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'tax_number' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            if (empty($organization->slug)) {
                $baseSlug = Str::slug($organization->name);
                $slug = $baseSlug;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }
                $organization->slug = $slug;
            }
        });

        static::created(function (Organization $org): void {
            if (!empty($org->country_code)) {
                app(\App\Services\Core\SettingsService::class)
                    ->initializeByCountry($org->id, $org->country_code, false);
            }
        });
    }

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_organization_id');
    }

    public function subsidiaries(): HasMany
    {
        return $this->hasMany(self::class, 'parent_organization_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    // Accessors
    public function getDefaultBranchAttribute(): ?Branch
    {
        return $this->branches()->where('is_default', true)->first();
    }

    // Methods

    /**
     * The ids of the organizations sharing $organizationId's group.
     *
     * A group's root is parent_organization_id when the organization has a
     * parent and its own id otherwise, and two organizations share a group
     * when their roots match. So a parent and all its subsidiaries form one
     * group, and an organization with neither a parent nor a subsidiary is a
     * group of one. An id that names no organization has an empty group,
     * which accepts nothing.
     *
     * @return list<int>
     */
    public static function groupIds(int $organizationId): array
    {
        $organization = static::query()->select('id', 'parent_organization_id')->find($organizationId);

        if ($organization === null) {
            return [];
        }

        $rootId = $organization->parent_organization_id ?? $organization->id;

        return static::query()
            ->where(fn ($query) => $query->whereKey($rootId)->orWhere('parent_organization_id', $rootId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Countries whose invoices this ERP files with a tax authority.
     *
     * Saudi Arabia only, because the submission path carries no jurisdiction:
     * PostInvoiceOrchestrator calls MasaarClient::submitInvoice(), which posts
     * to /pipeline/submit with no country and a circuit breaker keyed 'zatca'.
     * Every country on this list is therefore filed with ZATCA.
     *
     * Adding one is not enough to support it. The platform needs a compliance
     * profile for that jurisdiction and the partner API needs to route on it;
     * without both, a country listed here has its invoices misfiled rather
     * than left alone.
     */
    private const COMPLIANCE_COUNTRIES = ['SA'];

    public function requiresCompliance(): bool
    {
        return in_array($this->country_code, self::COMPLIANCE_COUNTRIES, true);
    }

    public function getTaxSchemeDetails(): array
    {
        return match ($this->tax_scheme) {
            'VAT' => [
                'name' => 'Value Added Tax',
                'rate' => $this->getStandardVatRate(),
            ],
            'GST' => [
                'name' => 'Goods and Services Tax',
                'slabs' => [0, 5, 12, 18, 28],
            ],
            default => [
                'name' => 'No Tax',
                'rate' => 0,
            ],
        };
    }

    public function getStandardVatRate(): float
    {
        return match ($this->country_code) {
            'SA' => 15.0,
            'AE', 'BH', 'OM' => 5.0,
            'QA', 'KW' => 0.0,
            default => 0.0,
        };
    }

}
