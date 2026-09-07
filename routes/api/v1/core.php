<?php

use App\Http\Controllers\Api\V1\Core\BusinessPartnerController;
use App\Http\Controllers\Api\V1\Core\ChangeTransportController;
use App\Http\Controllers\Api\V1\Core\DashboardController;
use App\Http\Controllers\Api\V1\Core\DocumentRetentionController;
use App\Http\Controllers\Api\V1\Core\ExportController;
use App\Http\Controllers\Api\V1\Core\GdprController;
use App\Http\Controllers\Api\V1\Core\ImportController;
use App\Http\Controllers\Api\V1\Core\IpAllowlistController;
use App\Http\Controllers\Api\V1\Core\JobMonitorController;
use App\Http\Controllers\Api\V1\Core\LocalizationController;
use App\Http\Controllers\Api\V1\Core\ModuleController;
use App\Http\Controllers\Api\V1\Core\NotificationController;
use App\Http\Controllers\Api\V1\Core\PrintController;
use App\Http\Controllers\Api\V1\Core\RoleController;
use App\Http\Controllers\Api\V1\Core\SensitiveAccessController;
use App\Http\Controllers\Api\V1\Core\SensitiveAccessReportController;
use App\Http\Controllers\Api\V1\Core\SettingsController;
use App\Http\Controllers\Api\V1\Core\TenantRateLimitController;
use App\Http\Controllers\Api\V1\Core\UserController;
use App\Http\Controllers\Api\V1\Core\UserEventsController;
use App\Http\Controllers\Api\V1\Core\WebhookController;
use App\Http\Controllers\Api\V1\Core\WebhookDlqController;
use App\Http\Resources\BranchResource;
use App\Http\Resources\OrganizationResource;
use App\Models\Core\Branch;
use App\Models\Core\Permission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core Module Routes
|--------------------------------------------------------------------------
|
| Routes for organizations, branches, users, roles, and permissions.
| These routes require authentication and organization context.
|
*/

// Sensitive data re-authentication and reveal routes
Route::middleware(['auth:api', 'validate.jwt', 'check.organization', 'throttle:10,1'])->group(function () {
    Route::post('/sensitive/request-access', [SensitiveAccessController::class, 'requestAccess'])
        ->name('sensitive.request-access');
    Route::get('/sensitive/{resourceType}/{resourceId}/reveal', [SensitiveAccessController::class, 'reveal'])
        ->name('sensitive.reveal');
});

// Organization routes (read-only for regular users)
Route::prefix('organization')->group(function () {
    Route::get('/', function () {
        $organization = auth()->user()->organization;

        return response()->json([
            'success' => true,
            'message' => 'Organization retrieved successfully',
            'data' => new OrganizationResource($organization),
        ]);
    })->name('organization.show');
});

// Branches routes
Route::prefix('branches')->group(function () {
    Route::get('/', function () {
        $branches = auth()->user()->branches;

        return response()->json([
            'success' => true,
            'message' => 'Branches retrieved successfully',
            'data' => BranchResource::collection($branches),
        ]);
    })->name('branches.index');

    Route::post('/{branch}/set-default', function (Branch $branch) {
        auth()->user()->setDefaultBranch($branch);

        return response()->json([
            'success' => true,
            'message' => 'Default branch updated',
        ]);
    })->name('branches.set-default');
});

// Users routes
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index'])->middleware(['check.permission:core.users.view', 'throttle:60,1'])->name('users.index');
    Route::post('/', [UserController::class, 'store'])->middleware('check.permission:core.users.create')->name('users.store');
    Route::get('/{user}', [UserController::class, 'show'])->middleware('check.permission:core.users.view')->name('users.show');
    Route::put('/{user}', [UserController::class, 'update'])->middleware('check.permission:core.users.edit')->name('users.update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('check.permission:core.users.delete')->name('users.destroy');
});

// Roles routes
Route::prefix('roles')->group(function () {
    Route::get('/', [RoleController::class, 'index'])->middleware(['check.permission:core.roles.view', 'throttle:60,1'])->name('roles.index');
    Route::post('/', [RoleController::class, 'store'])->middleware('check.permission:core.roles.create')->name('roles.store');
    Route::get('/{role}', [RoleController::class, 'show'])->middleware('check.permission:core.roles.view')->name('roles.show');
    Route::put('/{role}', [RoleController::class, 'update'])->middleware('check.permission:core.roles.edit')->name('roles.update');
    Route::delete('/{role}', [RoleController::class, 'destroy'])->middleware('check.permission:core.roles.delete')->name('roles.destroy');
});

// Permissions routes
Route::prefix('permissions')->group(function () {
    Route::get('/', function () {
        $permissions = Permission::all()->groupBy('module');

        return response()->json([
            'success' => true,
            'message' => 'Permissions retrieved successfully',
            'data' => $permissions,
        ]);
    })->middleware('check.permission:core.roles.view')->name('permissions.index');
});

// Settings routes
Route::prefix('settings')->group(function () {
    // Organization settings
    Route::get('/', [SettingsController::class, 'index'])
        ->middleware('check.permission:core.settings.view')
        ->name('settings.index');

    Route::get('/definitions', [SettingsController::class, 'getDefinitions'])
        ->name('settings.definitions')->middleware('check.permission:core.settings.view');

    Route::put('/bulk', [SettingsController::class, 'updateMany'])
        ->middleware('check.permission:core.settings.edit')
        ->name('settings.update-many');

    // Regional settings — placed before /{key} wildcard to avoid shadowing
    Route::get('/regions', [SettingsController::class, 'regions'])
        ->middleware('check.permission:core.settings.view')
        ->name('core.settings.regions');

    Route::get('/regions/{countryCode}/preview', [SettingsController::class, 'previewRegionDefaults'])
        ->middleware('check.permission:core.settings.view')
        ->name('core.settings.region-preview');

    Route::post('/initialize-region', [SettingsController::class, 'initializeRegion'])
        ->middleware('check.permission:core.settings.edit')
        ->name('core.settings.initialize-region');

    Route::post('/bulk-reset-to-region', [SettingsController::class, 'resetToRegion'])
        ->middleware('check.permission:core.settings.edit')
        ->name('core.settings.reset-to-region');

    Route::get('/group/{group}', [SettingsController::class, 'getGroup'])
        ->middleware('check.permission:core.settings.view')
        ->name('settings.group.show');

    Route::put('/group/{group}', [SettingsController::class, 'updateGroup'])
        ->middleware('check.permission:core.settings.edit')
        ->name('settings.group.update');

    Route::get('/{key}', [SettingsController::class, 'show'])
        ->middleware('check.permission:core.settings.view')
        ->name('settings.show');

    Route::put('/{key}', [SettingsController::class, 'update'])
        ->middleware('check.permission:core.settings.edit')
        ->name('settings.update');

    Route::delete('/{key}', [SettingsController::class, 'reset'])
        ->middleware('check.permission:core.settings.edit')
        ->name('settings.reset');

    // Cache management
    Route::post('/cache/clear', [SettingsController::class, 'clearCache'])
        ->middleware('check.permission:core.settings.edit')
        ->name('settings.cache.clear');
});

// User preferences routes (personal settings)
Route::prefix('preferences')->group(function () {
    Route::get('/', [SettingsController::class, 'getUserPreferences'])
        ->name('preferences.index')->middleware('check.permission:core.settings.view');

    Route::put('/bulk', [SettingsController::class, 'setUserPreferences'])
        ->name('preferences.update-many');

    Route::get('/{key}', [SettingsController::class, 'getUserPreference'])
        ->name('preferences.show')->middleware('check.permission:core.settings.view');

    Route::put('/{key}', [SettingsController::class, 'setUserPreference'])
        ->name('preferences.update');

    Route::delete('/{key}', [SettingsController::class, 'deleteUserPreference'])
        ->name('preferences.delete');
});

// Feature flags routes
Route::prefix('features')->group(function () {
    Route::get('/', [SettingsController::class, 'getFeatures'])
        ->middleware('check.permission:core.settings.view')
        ->name('features.index');

    Route::get('/available', [SettingsController::class, 'getAvailableFeatures'])
        ->middleware('check.permission:features.view')
        ->name('features.available');

    Route::get('/{feature}', [SettingsController::class, 'checkFeature'])
        ->name('features.check')->middleware('check.permission:core.settings.view');

    Route::post('/{feature}/enable', [SettingsController::class, 'enableFeature'])
        ->middleware('check.permission:core.settings.edit')
        ->name('features.enable');

    Route::post('/{feature}/disable', [SettingsController::class, 'disableFeature'])
        ->middleware('check.permission:core.settings.edit')
        ->name('features.disable');
});

// Number sequences routes
Route::prefix('sequences')->group(function () {
    Route::get('/', [SettingsController::class, 'getNumberSequences'])
        ->middleware('check.permission:core.settings.view')
        ->name('sequences.index');

    Route::get('/types', [SettingsController::class, 'getSequenceTypes'])
        ->middleware('check.permission:core.settings.view')
        ->name('sequences.types');

    Route::get('/{type}', [SettingsController::class, 'getNumberSequence'])
        ->middleware('check.permission:core.settings.view')
        ->name('sequences.show');

    Route::put('/{type}', [SettingsController::class, 'updateNumberSequence'])
        ->middleware('check.permission:core.settings.edit')
        ->name('sequences.update');

    Route::get('/{type}/preview', [SettingsController::class, 'previewNextNumber'])
        ->middleware('check.permission:core.settings.view')
        ->name('sequences.preview');
});

// Print routes
Route::prefix('print')->group(function () {
    // Templates management
    Route::get('/templates', [PrintController::class, 'templates'])
        ->middleware('check.permission:core.settings.view')
        ->name('print.templates.index');

    Route::post('/templates', [PrintController::class, 'storeTemplate'])
        ->middleware('check.permission:core.settings.edit')
        ->name('print.templates.store');

    Route::get('/templates/{id}', [PrintController::class, 'showTemplate'])
        ->middleware('check.permission:core.settings.view')
        ->name('print.templates.show');

    Route::put('/templates/{id}', [PrintController::class, 'updateTemplate'])
        ->middleware('check.permission:core.settings.edit')
        ->name('print.templates.update');

    Route::delete('/templates/{id}', [PrintController::class, 'destroyTemplate'])
        ->middleware('check.permission:core.settings.edit')
        ->name('print.templates.destroy');

    Route::post('/templates/initialize', [PrintController::class, 'initializeDefaults'])
        ->middleware('check.permission:core.settings.edit')
        ->name('print.templates.initialize');

    // Printer configurations
    Route::get('/configurations', [PrintController::class, 'configurations'])
        ->middleware('check.permission:core.settings.view')
        ->name('print.configurations.index');

    Route::post('/configurations', [PrintController::class, 'storeConfiguration'])
        ->middleware('check.permission:core.settings.edit')
        ->name('print.configurations.store');

    Route::put('/configurations/{id}', [PrintController::class, 'updateConfiguration'])
        ->middleware('check.permission:core.settings.edit')
        ->name('print.configurations.update');

    // Document printing endpoints
    Route::get('/invoice/{id}', [PrintController::class, 'invoice'])
        ->middleware('check.permission:sales.invoices.view')
        ->name('print.invoice');

    Route::get('/quotation/{id}', [PrintController::class, 'quotation'])
        ->middleware('check.permission:sales.quotations.view')
        ->name('print.quotation');

    Route::get('/payment-receipt/{id}', [PrintController::class, 'paymentReceipt'])
        ->middleware('check.permission:sales.payments.view')
        ->name('print.payment-receipt');

    Route::get('/purchase-order/{id}', [PrintController::class, 'purchaseOrder'])
        ->middleware('check.permission:purchase.orders.view')
        ->name('print.purchase-order');

    // Batch printing
    Route::post('/batch', [PrintController::class, 'batch'])
        ->middleware('check.permission:core.settings.edit')
        ->name('print.batch');
});

// Localization routes
Route::prefix('localization')->group(function () {
    // Get all localization data for frontend
    Route::get('/', [LocalizationController::class, 'index'])
        ->name('localization.index')->middleware('check.permission:core.settings.view');

    // Languages
    Route::get('/languages', [LocalizationController::class, 'languages'])
        ->name('localization.languages')->middleware('check.permission:core.settings.view');

    // Set user language preference
    Route::post('/language', [LocalizationController::class, 'setUserLanguage'])
        ->name('localization.set-language');

    // Translations
    Route::get('/translations/{languageCode}', [LocalizationController::class, 'translations'])
        ->name('localization.translations')->middleware('check.permission:core.settings.view');

    Route::get('/translations/{languageCode}/{group}', [LocalizationController::class, 'translationGroup'])
        ->name('localization.translations.group')->middleware('check.permission:core.settings.view');

    Route::post('/translations', [LocalizationController::class, 'updateTranslation'])
        ->middleware('check.permission:core.settings.edit')
        ->name('localization.translations.update');

    Route::post('/translations/bulk', [LocalizationController::class, 'updateTranslations'])
        ->middleware('check.permission:core.settings.edit')
        ->name('localization.translations.update-bulk');

    Route::get('/translation-groups', [LocalizationController::class, 'translationGroups'])
        ->name('localization.translation-groups')->middleware('check.permission:core.settings.view');

    // Branding
    Route::get('/branding', [LocalizationController::class, 'getBranding'])
        ->middleware('check.permission:core.settings.view')
        ->name('localization.branding');

    Route::put('/branding', [LocalizationController::class, 'updateBranding'])
        ->middleware('check.permission:core.settings.edit')
        ->name('localization.branding.update');

    Route::post('/branding/logo', [LocalizationController::class, 'uploadLogo'])
        ->middleware('check.permission:core.settings.edit')
        ->name('localization.branding.upload-logo');
});

// Dashboard routes
Route::prefix('dashboard')->group(function () {
    // Main dashboard data
    Route::get('/', [DashboardController::class, 'index'])

        ->name('dashboard.index')->middleware('check.permission:core.dashboards.view');

    // Quick stats (combined overview from all modules)
    Route::get('/quick-stats', [DashboardController::class, 'quickStats'])

        ->name('dashboard.quick-stats')->middleware('check.permission:core.dashboards.view');

    // Available widgets
    Route::get('/widgets', [DashboardController::class, 'widgets'])

        ->name('dashboard.widgets')->middleware('check.permission:core.dashboards.view');

    // Single widget data
    Route::get('/widgets/{widgetCode}', [DashboardController::class, 'widget'])

        ->name('dashboard.widget')->middleware('check.permission:core.dashboards.view');

    // Layouts
    Route::get('/layouts', [DashboardController::class, 'layouts'])

        ->name('dashboard.layouts')->middleware('check.permission:core.dashboards.view');

    Route::post('/layouts', [DashboardController::class, 'createLayout'])

        ->name('dashboard.layouts.create')->middleware('check.permission:core.dashboards.manage');

    Route::get('/layouts/{id}', [DashboardController::class, 'layout'])

        ->name('dashboard.layouts.show')->middleware('check.permission:core.dashboards.view');

    Route::put('/layouts/{id}', [DashboardController::class, 'updateLayout'])

        ->name('dashboard.layouts.update')->middleware('check.permission:core.dashboards.manage');

    Route::delete('/layouts/{id}', [DashboardController::class, 'deleteLayout'])

        ->name('dashboard.layouts.delete')->middleware('check.permission:core.dashboards.manage');

    // Widget management in layouts
    Route::post('/layouts/{layoutId}/widgets', [DashboardController::class, 'addWidget'])

        ->name('dashboard.layouts.add-widget')->middleware('check.permission:core.dashboards.manage');

    Route::delete('/layouts/{layoutId}/widgets/{widgetCode}', [DashboardController::class, 'removeWidget'])

        ->name('dashboard.layouts.remove-widget')->middleware('check.permission:core.dashboards.manage');

    Route::put('/layouts/{layoutId}/widgets/{widgetCode}/position', [DashboardController::class, 'updateWidgetPosition'])

        ->name('dashboard.layouts.widget-position')->middleware('check.permission:core.dashboards.manage');

    // Reset layout to default
    Route::post('/layouts/{type}/reset', [DashboardController::class, 'resetLayout'])

        ->name('dashboard.layouts.reset')->middleware('check.permission:core.dashboards.manage');
});

// Module Management routes
Route::prefix('modules')->group(function () {
    // Get all available modules and their status
    Route::get('/', [ModuleController::class, 'index'])

        ->name('modules.index')->middleware('check.permission:core.users.view');

    // Get enabled modules for current user (for navigation)
    Route::get('/user', [ModuleController::class, 'userModules'])

        ->name('modules.user')->middleware('check.permission:core.users.view');

    // Get module summary for dashboard
    Route::get('/summary', [ModuleController::class, 'summary'])

        ->name('modules.summary')->middleware('check.permission:core.users.view');

    // Get subscription tiers
    Route::get('/tiers', [ModuleController::class, 'tiers'])

        ->name('modules.tiers')->middleware('check.permission:core.users.view');

    // Check if module is enabled
    Route::get('/check/{moduleCode}', [ModuleController::class, 'check'])

        ->name('modules.check')->middleware('check.permission:core.users.view');

    // Check if feature is enabled
    Route::get('/check/{moduleCode}/{feature}', [ModuleController::class, 'checkFeature'])
        ->name('modules.check-feature')->middleware('check.permission:core.users.view');

    Route::patch('/{moduleCode}/active', [ModuleController::class, 'setActive'])
        ->middleware('check.permission:core.settings.edit')
        ->name('modules.active');

    // Update module features (admin only)
    Route::put('/{moduleCode}/features', [ModuleController::class, 'updateFeatures'])
        ->middleware('check.permission:core.settings.edit')
        ->name('modules.update-features');

    // User-specific module access
    Route::get('/users/{userId}/access', [ModuleController::class, 'getUserAccess'])
        ->name('modules.user-access.show')->middleware('check.permission:core.users.view');

    Route::put('/users/{userId}/access', [ModuleController::class, 'setUserAccess'])
        ->middleware('check.permission:core.users.edit')
        ->name('modules.user-access.update');

    Route::delete('/users/{userId}/access', [ModuleController::class, 'clearUserAccess'])
        ->middleware('check.permission:core.users.edit')
        ->name('modules.user-access.clear');
});

// Notification routes
Route::prefix('notifications')->group(function () {
    // Get user notifications
    Route::get('/', [NotificationController::class, 'index'])
        ->name('notifications.index');

    // Get unread count
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])
        ->name('notifications.unread-count');

    // Get notification types
    Route::get('/types', [NotificationController::class, 'types'])
        ->name('notifications.types');

    // Mark all as read
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.mark-all-read');

    // Notification preferences
    Route::get('/preferences', [NotificationController::class, 'preferences'])
        ->name('notifications.preferences');

    Route::put('/preferences', [NotificationController::class, 'updatePreferences'])
        ->name('notifications.preferences.update');

    Route::put('/preferences/{type}', [NotificationController::class, 'updatePreference'])
        ->name('notifications.preferences.update-single');

    Route::post('/preferences/initialize', [NotificationController::class, 'initializePreferences'])
        ->name('notifications.preferences.initialize');

    // Single notification actions
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.mark-read');

    Route::delete('/{id}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy');
});

// Import routes
Route::prefix('imports')->group(function () {
    // Get available entity types for import
    Route::get('/entity-types', [ImportController::class, 'entityTypes'])
        ->name('imports.entity-types')->middleware('check.permission:core.imports.view');

    // Get import history
    Route::get('/history', [ImportController::class, 'history'])
        ->name('imports.history')->middleware('check.permission:core.imports.view');

    // Download sample import template
    Route::get('/sample/{entityType}', [ImportController::class, 'sampleTemplate'])
        ->name('imports.sample-template')->middleware('check.permission:core.imports.view');

    // Import templates management
    Route::get('/templates', [ImportController::class, 'templates'])
        ->name('imports.templates.index')->middleware('check.permission:core.imports.view');

    Route::post('/templates', [ImportController::class, 'saveTemplate'])
        ->name('imports.templates.store')->middleware('check.permission:core.imports.manage');

    // Upload file for import
    Route::post('/upload', [ImportController::class, 'upload'])
        ->name('imports.upload')->middleware('check.permission:core.imports.manage');

    // Import job operations
    Route::get('/{uuid}', [ImportController::class, 'status'])
        ->name('imports.status')->middleware('check.permission:core.imports.view');

    Route::get('/{uuid}/preview', [ImportController::class, 'preview'])
        ->name('imports.preview')->middleware('check.permission:core.imports.view');

    Route::post('/{uuid}/configure', [ImportController::class, 'configure'])
        ->name('imports.configure')->middleware('check.permission:core.imports.manage');

    Route::post('/{uuid}/process', [ImportController::class, 'process'])
        ->name('imports.process')->middleware('check.permission:core.imports.manage');

    Route::post('/{uuid}/cancel', [ImportController::class, 'cancel'])
        ->name('imports.cancel')->middleware('check.permission:core.imports.manage');
});

// Export routes
Route::prefix('exports')->group(function () {
    // Get available entity types for export
    Route::get('/entity-types', [ExportController::class, 'entityTypes'])
        ->name('exports.entity-types')->middleware('check.permission:core.exports.view');

    // Get export history
    Route::get('/history', [ExportController::class, 'history'])
        ->name('exports.history')->middleware('check.permission:core.exports.view');

    // Create export job
    Route::post('/', [ExportController::class, 'create'])
        ->name('exports.create')->middleware('check.permission:core.exports.manage');

    // Quick export (immediate download)
    Route::post('/quick', [ExportController::class, 'quickExport'])
        ->name('exports.quick')->middleware('check.permission:core.exports.manage');

    // Export job operations
    Route::get('/{uuid}', [ExportController::class, 'status'])
        ->name('exports.status')->middleware('check.permission:core.exports.view');

    Route::get('/{uuid}/download', [ExportController::class, 'download'])
        ->name('api.v1.exports.download')->middleware('check.permission:core.exports.view');
});

// Webhook routes. {id} is numeric so /webhooks/dlq, registered below,
// is not swallowed by /webhooks/{id}.
Route::prefix('webhooks')->whereNumber('id')->group(function () {
    // Get available webhook events
    Route::get('/events', [WebhookController::class, 'events'])
        ->name('webhooks.events')->middleware('check.permission:core.settings.view');

    // Get recent event history
    Route::get('/events/history', [WebhookController::class, 'events_history'])
        ->name('webhooks.events.history')->middleware('check.permission:core.settings.view');

    // List webhooks
    Route::get('/', [WebhookController::class, 'index'])
        ->middleware('check.permission:core.settings.view')
        ->name('webhooks.index');

    // Create webhook
    Route::post('/', [WebhookController::class, 'store'])
        ->middleware('check.permission:core.settings.edit')
        ->name('webhooks.store');

    // Webhook operations
    Route::get('/{id}', [WebhookController::class, 'show'])
        ->middleware('check.permission:core.settings.view')
        ->name('webhooks.show');

    Route::put('/{id}', [WebhookController::class, 'update'])
        ->middleware('check.permission:core.settings.edit')
        ->name('webhooks.update');

    Route::delete('/{id}', [WebhookController::class, 'destroy'])
        ->middleware('check.permission:core.settings.edit')
        ->name('webhooks.destroy');

    // Test webhook
    Route::post('/{id}/test', [WebhookController::class, 'test'])
        ->middleware('check.permission:core.settings.edit')
        ->name('webhooks.test');

    // Toggle webhook status
    Route::post('/{id}/toggle', [WebhookController::class, 'toggle'])
        ->middleware('check.permission:core.settings.edit')
        ->name('webhooks.toggle');

    // Regenerate secret
    Route::post('/{id}/regenerate-secret', [WebhookController::class, 'regenerateSecret'])
        ->middleware('check.permission:core.settings.edit')
        ->name('webhooks.regenerate-secret');

    // Delivery history
    Route::get('/{id}/deliveries', [WebhookController::class, 'deliveries'])
        ->middleware('check.permission:core.settings.view')
        ->name('webhooks.deliveries');

    Route::get('/{id}/deliveries/{deliveryId}', [WebhookController::class, 'deliveryDetails'])
        ->middleware('check.permission:core.settings.view')
        ->name('webhooks.deliveries.show');

    Route::post('/{id}/deliveries/{deliveryId}/retry', [WebhookController::class, 'retryDelivery'])
        ->middleware('check.permission:core.settings.edit')
        ->name('webhooks.deliveries.retry');
});

// User event tracking routes
Route::prefix('events')->group(function () {
    Route::get('/', [UserEventsController::class, 'index'])->name('events.index');
    Route::get('/summary', [UserEventsController::class, 'summary'])->name('events.summary');
});

// Document Retention Policy routes (Gap 15)
Route::prefix('retention')->group(function () {
    Route::apiResource('policies', DocumentRetentionController::class)
        ->middleware([
            'index' => 'check.permission:core.settings.view',
            'store' => 'check.permission:core.settings.edit',
            'show' => 'check.permission:core.settings.view',
            'update' => 'check.permission:core.settings.edit',
            'destroy' => 'check.permission:core.settings.edit',
        ])
        ->names('core.retention.policies');

    Route::get('legal-holds', [DocumentRetentionController::class, 'legalHoldsIndex'])
        ->middleware('check.permission:core.settings.view')
        ->name('core.retention.holds.index');

    Route::post('legal-holds', [DocumentRetentionController::class, 'placeLegalHold'])
        ->middleware('check.permission:core.settings.edit')
        ->name('core.retention.holds.place');

    Route::delete('legal-holds/{documentLegalHold}', [DocumentRetentionController::class, 'releaseLegalHold'])
        ->middleware('check.permission:core.settings.edit')
        ->name('core.retention.holds.release');

    Route::post('run', [DocumentRetentionController::class, 'runSchedule'])
        ->middleware('check.permission:core.settings.edit')
        ->name('core.retention.run');

    Route::get('expiring', [DocumentRetentionController::class, 'expiringDocuments'])
        ->middleware('check.permission:core.settings.view')
        ->name('core.retention.expiring');
});

// Sensitive Access Reporting routes (Gap 20)
Route::prefix('sensitive-access')->group(function () {
    Route::get('report', [SensitiveAccessReportController::class, 'report'])
        ->middleware('check.permission:core.users.view')
        ->name('core.sensitive-access.report');

    Route::get('document/{type}/{id}', [SensitiveAccessReportController::class, 'documentAccess'])
        ->middleware('check.permission:core.users.view')
        ->name('core.sensitive-access.document');

    Route::get('suspicious', [SensitiveAccessReportController::class, 'suspiciousActivity'])
        ->middleware('check.permission:core.users.view')
        ->name('core.sensitive-access.suspicious');
});

// Change Transport (SAP CTS equivalent)
Route::prefix('change-transport')->name('core.change-transport.')->group(function () {
    Route::get('/', [ChangeTransportController::class, 'index'])->name('index')->middleware('super.admin');
    Route::post('/', [ChangeTransportController::class, 'store'])->name('store')->middleware('super.admin');
    Route::get('/open', [ChangeTransportController::class, 'openRequests'])->name('open')->middleware('super.admin');
    Route::get('/{id}', [ChangeTransportController::class, 'show'])->name('show')->middleware('super.admin');
    Route::put('/{id}', [ChangeTransportController::class, 'update'])->name('update')->middleware('super.admin');
    Route::get('/{id}/objects', [ChangeTransportController::class, 'objects'])->name('objects')->middleware('super.admin');
    Route::post('/{id}/objects', [ChangeTransportController::class, 'addObject'])->name('objects.add')->middleware('super.admin');
    Route::post('/{id}/release', [ChangeTransportController::class, 'release'])->name('release')->middleware('super.admin');
    Route::post('/{id}/import', [ChangeTransportController::class, 'import'])->name('import')->middleware('super.admin');
    Route::post('/{id}/rollback', [ChangeTransportController::class, 'rollback'])->name('rollback')->middleware('super.admin');
    Route::get('/{id}/history', [ChangeTransportController::class, 'history'])->name('history')->middleware('super.admin');
});

// Job Monitor (SAP SM37 equivalent)
Route::prefix('job-monitor')->name('core.job-monitor.')->group(function () {
    Route::get('/', [JobMonitorController::class, 'index'])->name('index')->middleware('super.admin');
    Route::get('/stats', [JobMonitorController::class, 'stats'])->name('stats')->middleware('super.admin');
    Route::get('/running', [JobMonitorController::class, 'running'])->name('running')->middleware('super.admin');
    Route::get('/failed', [JobMonitorController::class, 'failed'])->name('failed')->middleware('super.admin');
    Route::post('/cleanup', [JobMonitorController::class, 'cleanup'])->name('cleanup')->middleware('super.admin');
    Route::get('/{id}', [JobMonitorController::class, 'show'])->name('show')->middleware('super.admin');
    Route::get('/{id}/logs', [JobMonitorController::class, 'logs'])->name('logs')->middleware('super.admin');
    Route::post('/{id}/retry', [JobMonitorController::class, 'retry'])->name('retry')->middleware('super.admin');
});

/*
|--------------------------------------------------------------------------
| GDPR / Data Privacy (Platform)
|--------------------------------------------------------------------------
*/
Route::prefix('gdpr')->name('core.gdpr.')->middleware(['auth:api'])->group(function (): void {
    Route::get('/requests', [GdprController::class, 'requests'])->name('requests.index')->middleware('check.permission:core.gdpr.view');
    Route::post('/requests', [GdprController::class, 'submitRequest'])->name('requests.store')->middleware('check.permission:core.gdpr.manage');
    Route::put('/requests/{id}/process', [GdprController::class, 'processRequest'])->name('requests.process')->middleware('check.permission:core.gdpr.manage');
    Route::get('/processing-register', [GdprController::class, 'processingRegister'])->name('register')->middleware('check.permission:core.gdpr.view');
    Route::post('/processing-register', [GdprController::class, 'storeActivity'])->name('register.store')->middleware('check.permission:core.gdpr.manage');
    Route::post('/consent', [GdprController::class, 'recordConsent'])->name('consent.store')->middleware('check.permission:core.gdpr.manage');
    Route::delete('/consent/{id}', [GdprController::class, 'withdrawConsent'])->name('consent.withdraw')->middleware('check.permission:core.gdpr.manage');
});

/*
|--------------------------------------------------------------------------
| Webhook Dead-Letter Queue (Platform)
|--------------------------------------------------------------------------
*/
Route::prefix('webhooks/dlq')->name('core.webhooks.dlq.')->middleware(['auth:api'])->group(function (): void {
    Route::get('/', [WebhookDlqController::class, 'index'])->name('index')->middleware('check.permission:core.webhooks.view');
    Route::get('/summary', [WebhookDlqController::class, 'summary'])->name('summary')->middleware('check.permission:core.webhooks.view');
    Route::post('/{id}/replay', [WebhookDlqController::class, 'replay'])->name('replay')->middleware('check.permission:core.webhooks.manage');
    Route::post('/bulk-replay', [WebhookDlqController::class, 'bulkReplay'])->name('bulk-replay')->middleware('check.permission:core.webhooks.manage');
    Route::delete('/{id}', [WebhookDlqController::class, 'destroy'])->name('destroy')->middleware('check.permission:core.webhooks.manage');
});

/*
|--------------------------------------------------------------------------
| IP Allowlisting (Platform)
|--------------------------------------------------------------------------
*/
Route::prefix('security/ip-allowlist')->name('core.ip-allowlist.')->middleware(['auth:api'])->group(function (): void {
    Route::get('/', [IpAllowlistController::class, 'index'])->name('index')->middleware('super.admin');
    Route::post('/', [IpAllowlistController::class, 'store'])->name('store')->middleware('super.admin');
    Route::put('/{id}', [IpAllowlistController::class, 'update'])->name('update')->middleware('super.admin');
    Route::delete('/{id}', [IpAllowlistController::class, 'destroy'])->name('destroy')->middleware('super.admin');
    Route::post('/check', [IpAllowlistController::class, 'check'])->name('check')->middleware('super.admin');
});

/*
|--------------------------------------------------------------------------
| Per-Tenant Rate Limits (Platform)
|--------------------------------------------------------------------------
*/
Route::prefix('rate-limits')->name('core.rate-limits.')->middleware(['auth:api'])->group(function (): void {
    Route::get('/', [TenantRateLimitController::class, 'show'])->name('show')->middleware('super.admin');
    Route::put('/', [TenantRateLimitController::class, 'update'])->name('update')->middleware('super.admin');
    Route::get('/stats', [TenantRateLimitController::class, 'stats'])->name('stats')->middleware('super.admin');
});

/*
|--------------------------------------------------------------------------
| Business Partner / CVI (Customer-Vendor Integration)
|--------------------------------------------------------------------------
*/
Route::prefix('business-partners')->name('core.bp.')->middleware(['auth:api'])->group(function (): void {
    Route::get('/', [BusinessPartnerController::class, 'index'])->name('index')->middleware('check.permission:core.business-partners.view');
    Route::post('/', [BusinessPartnerController::class, 'store'])->name('store')->middleware('check.permission:core.business-partners.manage');
    Route::get('/{businessPartner}', [BusinessPartnerController::class, 'show'])->name('show')->middleware('check.permission:core.business-partners.view');
    Route::put('/{businessPartner}', [BusinessPartnerController::class, 'update'])->name('update')->middleware('check.permission:core.business-partners.manage');
    Route::post('/{businessPartner}/roles', [BusinessPartnerController::class, 'assignRole'])->name('roles.assign')->middleware('check.permission:core.business-partners.manage');
    Route::delete('/{businessPartner}/roles/{roleCode}', [BusinessPartnerController::class, 'revokeRole'])->name('roles.revoke')->middleware('check.permission:core.business-partners.manage');
    Route::post('/{businessPartner}/merge', [BusinessPartnerController::class, 'merge'])->name('merge')->middleware('check.permission:core.business-partners.manage');
});
