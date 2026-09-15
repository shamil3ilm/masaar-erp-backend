<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Feature\Architecture\Concerns\ScansSource;

/**
 * Keeps controllers from growing new database queries.
 *
 * A controller should validate the request, call a service and shape the
 * response. A query written in the controller cannot be reused by a job, a
 * console command or another endpoint, and a rule enforced there, such as a
 * status check or a scope, is skipped by every other caller.
 *
 * A query counts when a controller makes a static builder call on a class that
 * resolves to App\Models (Invoice::where(), Invoice::findOrFail(),
 * \App\Models\Sales\Quotation::query()) or runs SQL through the DB facade
 * (DB::table(), DB::select()). Not counted: calls on a model instance or a
 * relation, which start from a model the route or a service already loaded,
 * and local scopes called statically, which a pattern cannot tell from other
 * static methods.
 *
 * OFFENDERS is exact per file. A new query fails until it moves into a service
 * or the count is raised; a count that drops fails until it is lowered.
 */
class ControllerQueryTest extends TestCase
{
    use ScansSource;

    /** Static calls that start or run an Eloquent query when made on a model class. */
    private const BUILDER_METHODS = 'query|where\w*|orWhere\w*|find\w*|first\w*|create\w*|forceCreate|insert\w*|'
        .'upsert|update\w*|increment|decrement|destroy|truncate|all|get|value|pluck|count|sum|avg|min|max|'
        .'exists|doesntExist|paginate|simplePaginate|cursorPaginate|chunk\w*|lazy\w*|cursor|with\w*|'
        .'onlyTrashed|has|doesntHave|orderBy\w*|latest|oldest|inRandomOrder|select\w*|distinct|join\w*|'
        .'leftJoin\w*|groupBy|having\w*|limit|take|skip|offset|lockForUpdate|sharedLock';

    /** DB facade calls that run SQL rather than manage a transaction or connection. */
    private const DB_METHODS = 'table|select|selectOne|scalar|insert|update|delete|statement|unprepared|query';

    /**
     * Controllers that query directly today, with how many calls each makes.
     *
     * @var array<string, int>
     */
    private const OFFENDERS = [
        'Api/V1/Admin/FeatureFlagController.php' => 3,
        'Api/V1/Admin/ImpersonationAuditController.php' => 2,
        'Api/V1/Admin/PlatformAdminController.php' => 3,
        'Api/V1/Admin/PlatformSettingsController.php' => 4,
        'Api/V1/Admin/SupportTicketController.php' => 4,
        'Api/V1/Admin/SystemAnnouncementController.php' => 2,
        'Api/V1/Aml/AmlController.php' => 5,
        'Api/V1/Analytics/UserAnalyticsController.php' => 7,
        'Api/V1/Auth/AuthController.php' => 14,
        'Api/V1/Automation/AutomationEmailTemplateController.php' => 2,
        'Api/V1/Automation/AutomationRuleController.php' => 1,
        'Api/V1/Automation/WorkflowController.php' => 9,
        'Api/V1/Billing/BillingInvoiceController.php' => 1,
        'Api/V1/Billing/SubscriptionController.php' => 6,
        'Api/V1/Billing/SubscriptionPlanController.php' => 2,
        'Api/V1/Billing/UsageController.php' => 3,
        'Api/V1/Budget/BudgetController.php' => 16,
        'Api/V1/Budget/BudgetTransferController.php' => 3,
        'Api/V1/CRM/ActivityController.php' => 5,
        'Api/V1/CRM/LeadController.php' => 1,
        'Api/V1/CRM/OpportunityController.php' => 2,
        'Api/V1/CRM/ServiceTicketController.php' => 2,
        'Api/V1/CRM/TerritoryController.php' => 12,
        'Api/V1/Calendar/CalendarController.php' => 1,
        'Api/V1/Calendar/CalendarEventController.php' => 1,
        'Api/V1/Campaign/CampaignController.php' => 7,
        'Api/V1/Campaign/SegmentController.php' => 6,
        'Api/V1/Compliance/DeniedPartyScreeningController.php' => 13,
        'Api/V1/Compliance/OnboardingController.php' => 4,
        'Api/V1/Compliance/ZatcaWebhookController.php' => 1,
        'Api/V1/Core/ActivityLogController.php' => 1,
        'Api/V1/Core/BusinessPartnerController.php' => 1,
        'Api/V1/Core/ChangeFreezeController.php' => 2,
        'Api/V1/Core/ChangeTransportController.php' => 9,
        'Api/V1/Core/ClassificationController.php' => 6,
        'Api/V1/Core/CustomFieldController.php' => 3,
        'Api/V1/Core/CustomerPortalController.php' => 1,
        'Api/V1/Core/DashboardController.php' => 13,
        'Api/V1/Core/DocumentDownloadController.php' => 1,
        'Api/V1/Core/DocumentRetentionController.php' => 2,
        'Api/V1/Core/EdiController.php' => 7,
        'Api/V1/Core/ExportController.php' => 2,
        'Api/V1/Core/FeatureFlagController.php' => 1,
        'Api/V1/Core/GdprController.php' => 4,
        'Api/V1/Core/ImportController.php' => 5,
        'Api/V1/Core/IpAllowlistController.php' => 3,
        'Api/V1/Core/JobMonitorController.php' => 4,
        'Api/V1/Core/ModuleAccessController.php' => 5,
        'Api/V1/Core/ModuleController.php' => 3,
        'Api/V1/Core/ModuleReadinessController.php' => 1,
        'Api/V1/Core/OnboardingController.php' => 1,
        'Api/V1/Core/PrintController.php' => 17,
        'Api/V1/Core/RoleController.php' => 4,
        'Api/V1/Core/SensitiveAccessController.php' => 3,
        'Api/V1/Core/SettingsController.php' => 5,
        'Api/V1/Core/UserEventsController.php' => 2,
        'Api/V1/Core/WebhookController.php' => 9,
        'Api/V1/Core/WebhookDlqController.php' => 4,
        'Api/V1/Core/WorkflowEscalationController.php' => 3,
        'Api/V1/Document/DocumentFolderController.php' => 1,
        'Api/V1/Ecommerce/EcommerceChannelController.php' => 1,
        'Api/V1/Ecommerce/EcommerceOrderController.php' => 2,
        'Api/V1/Ecommerce/OnlinePaymentController.php' => 1,
        'Api/V1/Ecommerce/PaymentGatewayController.php' => 2,
        'Api/V1/Expense/ExpenseBudgetController.php' => 1,
        'Api/V1/Expense/ExpenseCategoryController.php' => 2,
        'Api/V1/Expense/ExpenseController.php' => 2,
        'Api/V1/Expense/ExpenseReportController.php' => 1,
        'Api/V1/Fraud/FraudAlertController.php' => 8,
        'Api/V1/Inventory/BarcodeController.php' => 3,
        'Api/V1/Inventory/BatchClassificationController.php' => 8,
        'Api/V1/Inventory/BatchWhereUsedController.php' => 2,
        'Api/V1/Inventory/CategoryController.php' => 4,
        'Api/V1/Inventory/CrossDockingController.php' => 7,
        'Api/V1/Inventory/CycleCountController.php' => 7,
        'Api/V1/Inventory/EwmController.php' => 3,
        'Api/V1/Inventory/GoodsIssueController.php' => 1,
        'Api/V1/Inventory/HazmatController.php' => 7,
        'Api/V1/Inventory/MaterialValuationController.php' => 1,
        'Api/V1/Inventory/MovementTypeController.php' => 1,
        'Api/V1/Inventory/PhysicalInventoryController.php' => 1,
        'Api/V1/Inventory/PriceCheckController.php' => 3,
        'Api/V1/Inventory/ProductDetailController.php' => 1,
        'Api/V1/Inventory/ShelfLabelController.php' => 1,
        'Api/V1/Inventory/StockAdjustmentController.php' => 1,
        'Api/V1/Inventory/StockController.php' => 2,
        'Api/V1/Inventory/StockTransferController.php' => 2,
        'Api/V1/Inventory/StorageTypeController.php' => 8,
        'Api/V1/Inventory/WarehouseController.php' => 5,
        'Api/V1/Inventory/WarehouseTransferOrderController.php' => 1,
        'Api/V1/Inventory/WaveController.php' => 13,
        'Api/V1/Inventory/YardManagementController.php' => 12,
        'Api/V1/Maintenance/ConditionMaintenanceController.php' => 3,
        'Api/V1/Maintenance/CounterBasedMaintenanceController.php' => 8,
        'Api/V1/Maintenance/EquipmentHierarchyController.php' => 5,
        'Api/V1/Maintenance/FaultAnalysisController.php' => 4,
        'Api/V1/Maintenance/FleetController.php' => 15,
        'Api/V1/Maintenance/MaintenanceController.php' => 7,
        'Api/V1/Maintenance/MaintenancePermitController.php' => 9,
        'Api/V1/Maintenance/MaintenanceSettlementController.php' => 1,
        'Api/V1/Maintenance/MaintenanceTaskListController.php' => 2,
        'Api/V1/Maintenance/ServiceOrderController.php' => 2,
        'Api/V1/Manufacturing/AuditManagementController.php' => 11,
        'Api/V1/Manufacturing/BomAlternativeController.php' => 1,
        'Api/V1/Manufacturing/BomController.php' => 1,
        'Api/V1/Manufacturing/CalibrationController.php' => 12,
        'Api/V1/Manufacturing/CapaController.php' => 8,
        'Api/V1/Manufacturing/CapacityController.php' => 1,
        'Api/V1/Manufacturing/CoProductController.php' => 7,
        'Api/V1/Manufacturing/ComplaintController.php' => 6,
        'Api/V1/Manufacturing/DetailedSchedulingController.php' => 7,
        'Api/V1/Manufacturing/EngineeringChangeController.php' => 8,
        'Api/V1/Manufacturing/KanbanController.php' => 3,
        'Api/V1/Manufacturing/LongTermPlanningController.php' => 9,
        'Api/V1/Manufacturing/MrpController.php' => 10,
        'Api/V1/Manufacturing/ProcessOrderController.php' => 9,
        'Api/V1/Manufacturing/ProcurementInspectionController.php' => 8,
        'Api/V1/Manufacturing/ProductCostCollectorController.php' => 4,
        'Api/V1/Manufacturing/ProductCostingController.php' => 6,
        'Api/V1/Manufacturing/ProductionResourceToolController.php' => 6,
        'Api/V1/Manufacturing/ProductionVersionController.php' => 5,
        'Api/V1/Manufacturing/QInfoRecordController.php' => 3,
        'Api/V1/Manufacturing/QualityController.php' => 15,
        'Api/V1/Manufacturing/QualityCostController.php' => 3,
        'Api/V1/Manufacturing/RepetitiveManufacturingController.php' => 6,
        'Api/V1/Manufacturing/ReturnsInspectionController.php' => 2,
        'Api/V1/Manufacturing/ScrapReportingController.php' => 4,
        'Api/V1/Manufacturing/SkipLotController.php' => 4,
        'Api/V1/Manufacturing/StabilityStudyController.php' => 9,
        'Api/V1/Manufacturing/SubcontractingController.php' => 3,
        'Api/V1/Manufacturing/SupplierQualityController.php' => 7,
        'Api/V1/Manufacturing/WorkCenterController.php' => 1,
        'Api/V1/Manufacturing/WorkOrderController.php' => 2,
        'Api/V1/Messaging/ConversationController.php' => 9,
        'Api/V1/Messaging/MessageCampaignController.php' => 2,
        'Api/V1/Messaging/MessageTemplateController.php' => 1,
        'Api/V1/Messaging/MessagingConfigurationController.php' => 4,
        'Api/V1/Messaging/NotificationPreferenceController.php' => 6,
        'Api/V1/RealEstate/VacancyController.php' => 4,
        'Api/V1/Reports/ExportController.php' => 1,
        'Api/V1/Reports/ReportsController.php' => 9,
        'Api/V1/TM/TransportationController.php' => 1,
        'Api/V1/Tax/TaxDeterminationController.php' => 1,
        'Api/V1/Tax/VatReturnController.php' => 3,
    ];

    public function test_controllers_leave_queries_to_services(): void
    {
        $found = [];

        foreach ($this->phpFilesIn('Http/Controllers') as $relative => $path) {
            $count = $this->countQueries($this->codeOf($path));

            if ($count > 0) {
                $found[$relative] = $count;
            }
        }

        $this->assertCountsMatch(self::OFFENDERS, $found,
            "The direct queries in controllers have changed.\n"
            .'Put a new query in the service that owns the model, under app/Services/<Module>, '
            .'and call that from the controller. When a count drops, lower it here; when a '
            .'file reaches zero, remove it.');
    }

    public function test_detector_counts_only_model_queries(): void
    {
        $code = $this->withoutComments(<<<'PHP'
            <?php

            namespace App\Http\Controllers\Api\V1\Example;

            use App\Http\Controllers\Controller;
            use App\Models\Core;
            use App\Models\Sales\Invoice;
            use Illuminate\Support\Facades\Cache;
            use Illuminate\Support\Facades\DB;
            use Illuminate\Validation\Rule;

            class ExampleController extends Controller
            {
                public function counted(): void
                {
                    Invoice::where('status', 'draft')->get();
                    Invoice::findOrFail(1);
                    \App\Models\Sales\Quotation::query();
                    Core\User::with('roles')->first();
                    DB::table('invoices')->count();
                    DB::select('select 1');
                }

                public function ignored(Invoice $invoice): void
                {
                    Rule::exists('invoices', 'id');
                    Cache::get('key');
                    DB::transaction(fn () => null);
                    $invoice->update(['notes' => 'x']);
                    $invoice->lines()->where('id', 1)->get();
                    $status = Invoice::STATUS_DRAFT;
                    $class = Invoice::class;
                    // Invoice::where('id', 1);
                    static::find(1);
                }
            }
            PHP);

        $this->assertSame(6, $this->countQueries($code));
    }

    private function countQueries(string $code): int
    {
        $namespace = $this->namespaceOf($code);
        $imports = $this->importsOf($code);

        $pattern = '/(?<![\w\\\\$>:])(\\\\?[A-Z][\w\\\\]*)\s*::\s*('.self::BUILDER_METHODS.'|'.self::DB_METHODS.')\s*\(/';

        preg_match_all($pattern, $code, $calls, PREG_SET_ORDER);

        $count = 0;

        foreach ($calls as [, $name, $method]) {
            $class = $this->resolveClass($name, $imports, $namespace);

            $isModelQuery = str_starts_with($class, 'App\\Models\\')
                && preg_match('/^(?:'.self::BUILDER_METHODS.')$/', $method);

            $isRawQuery = in_array($class, ['Illuminate\\Support\\Facades\\DB', 'DB'], true)
                && preg_match('/^(?:'.self::DB_METHODS.')$/', $method);

            if ($isModelQuery || $isRawQuery) {
                $count++;
            }
        }

        return $count;
    }
}
