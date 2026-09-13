<?php

declare(strict_types=1);

namespace App\Models\Billing;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    // Feature codes, as stored in features and in a subscription's enabled_features
    public const FEATURE_MULTI_BRANCH = 'multi_branch';
    public const FEATURE_MULTI_WAREHOUSE = 'multi_warehouse';
    public const FEATURE_MULTI_CURRENCY = 'multi_currency';
    public const FEATURE_BATCH_TRACKING = 'batch_tracking';
    public const FEATURE_SERIAL_TRACKING = 'serial_tracking';
    public const FEATURE_ADVANCED_REPORTS = 'advanced_reports';
    public const FEATURE_CUSTOM_REPORTS = 'custom_reports';
    public const FEATURE_API_ACCESS = 'api_access';
    public const FEATURE_WEBHOOKS = 'webhooks';
    public const FEATURE_CUSTOM_BRANDING = 'custom_branding';
    public const FEATURE_WHITE_LABEL = 'white_label';
    public const FEATURE_PRIORITY_SUPPORT = 'priority_support';
    public const FEATURE_DEDICATED_SUPPORT = 'dedicated_support';
    public const FEATURE_DATA_EXPORT = 'data_export';
    public const FEATURE_AUDIT_LOG = 'audit_log';
    public const FEATURE_ADVANCED_PERMISSIONS = 'advanced_permissions';
    public const FEATURE_CUSTOM_FIELDS = 'custom_fields';
    public const FEATURE_DASHBOARD_CUSTOMIZATION = 'dashboard_customization';
    public const FEATURE_EMAIL_TEMPLATES = 'email_templates';
    public const FEATURE_SMS_NOTIFICATIONS = 'sms_notifications';
    public const FEATURE_RECURRING_INVOICES = 'recurring_invoices';
    public const FEATURE_CREDIT_NOTES = 'credit_notes';
    public const FEATURE_PURCHASE_MODULE = 'purchase_module';
    public const FEATURE_HR_MODULE = 'hr_module';
    public const FEATURE_MANUFACTURING_MODULE = 'manufacturing_module';
    public const FEATURE_CRM_MODULE = 'crm_module';
    public const FEATURE_POS_MODULE = 'pos_module';
    public const FEATURE_COMPLIANCE_INTEGRATION = 'compliance_integration';
    public const FEATURE_ECOMMERCE_INTEGRATION = 'ecommerce_integration';
    public const FEATURE_ACCOUNTING_INTEGRATION = 'accounting_integration';

    protected $fillable = [
        'name', 'code', 'description', 'tier', 'billing_cycle', 'base_price',
        'currency_code', 'max_users', 'max_branches', 'storage_limit_mb',
        'max_invoices_per_month', 'max_products', 'max_customers', 'max_employees',
        'api_calls_per_month', 'included_modules', 'features', 'trial_days',
        'trial_requires_card', 'is_public', 'is_popular', 'display_order', 'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'included_modules' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_popular' => 'boolean',
        'trial_requires_card' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganizationSubscription::class, 'plan_id');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(SubscriptionAddon::class, 'plan_id');
    }

    public function meteredPricingTiers(): HasMany
    {
        return $this->hasMany(MeteredPricingTier::class, 'plan_id');
    }

    public function hasFeature(string $featureCode): bool
    {
        return in_array($featureCode, $this->features ?? [], true);
    }

    /**
     * Every feature code with its display name.
     *
     * @return array<string, string>
     */
    public static function featureList(): array
    {
        return [
            self::FEATURE_MULTI_BRANCH => 'Multi-Branch Support',
            self::FEATURE_MULTI_WAREHOUSE => 'Multi-Warehouse Management',
            self::FEATURE_MULTI_CURRENCY => 'Multi-Currency Support',
            self::FEATURE_BATCH_TRACKING => 'Batch/Lot Tracking',
            self::FEATURE_SERIAL_TRACKING => 'Serial Number Tracking',
            self::FEATURE_ADVANCED_REPORTS => 'Advanced Reports',
            self::FEATURE_CUSTOM_REPORTS => 'Custom Report Builder',
            self::FEATURE_API_ACCESS => 'API Access',
            self::FEATURE_WEBHOOKS => 'Webhooks',
            self::FEATURE_CUSTOM_BRANDING => 'Custom Branding',
            self::FEATURE_WHITE_LABEL => 'White Label',
            self::FEATURE_PRIORITY_SUPPORT => 'Priority Support',
            self::FEATURE_DEDICATED_SUPPORT => 'Dedicated Account Manager',
            self::FEATURE_DATA_EXPORT => 'Data Export',
            self::FEATURE_AUDIT_LOG => 'Audit Log',
            self::FEATURE_ADVANCED_PERMISSIONS => 'Advanced Permissions',
            self::FEATURE_CUSTOM_FIELDS => 'Custom Fields',
            self::FEATURE_DASHBOARD_CUSTOMIZATION => 'Dashboard Customization',
            self::FEATURE_EMAIL_TEMPLATES => 'Email Templates',
            self::FEATURE_SMS_NOTIFICATIONS => 'SMS Notifications',
            self::FEATURE_RECURRING_INVOICES => 'Recurring Invoices',
            self::FEATURE_CREDIT_NOTES => 'Credit Notes',
            self::FEATURE_PURCHASE_MODULE => 'Purchase Module',
            self::FEATURE_HR_MODULE => 'HR & Payroll Module',
            self::FEATURE_MANUFACTURING_MODULE => 'Manufacturing Module',
            self::FEATURE_CRM_MODULE => 'CRM Module',
            self::FEATURE_POS_MODULE => 'Point of Sale Module',
            self::FEATURE_COMPLIANCE_INTEGRATION => 'Tax Compliance (ZATCA/GST)',
            self::FEATURE_ECOMMERCE_INTEGRATION => 'E-commerce Integration',
            self::FEATURE_ACCOUNTING_INTEGRATION => 'Accounting Integration',
        ];
    }
}
