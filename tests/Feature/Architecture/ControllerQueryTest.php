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
        'Api/V1/Accounting/AccountController.php' => 4,
        'Api/V1/Accounting/AccountGroupController.php' => 2,
        'Api/V1/Accounting/ActivityConfirmationController.php' => 3,
        'Api/V1/Accounting/AssessmentCycleController.php' => 2,
        'Api/V1/Accounting/AssetController.php' => 5,
        'Api/V1/Accounting/BankGuaranteeController.php' => 6,
        'Api/V1/Accounting/BankReconciliationController.php' => 2,
        'Api/V1/Accounting/CashFlowController.php' => 6,
        'Api/V1/Accounting/CheckManagementController.php' => 8,
        'Api/V1/Accounting/ConsolidationController.php' => 16,
        'Api/V1/Accounting/CopaController.php' => 1,
        'Api/V1/Accounting/CostCenterController.php' => 5,
        'Api/V1/Accounting/CostReconciliationController.php' => 1,
        'Api/V1/Accounting/CostSplittingController.php' => 3,
        'Api/V1/Accounting/CostingSheetController.php' => 7,
        'Api/V1/Accounting/CreditManagementController.php' => 4,
        'Api/V1/Accounting/DirectDebitController.php' => 8,
        'Api/V1/Accounting/DistributionCycleController.php' => 2,
        'Api/V1/Accounting/DocumentSplittingController.php' => 2,
        'Api/V1/Accounting/DocumentTypeController.php' => 2,
        'Api/V1/Accounting/DunningController.php' => 4,
        'Api/V1/Accounting/EbamController.php' => 1,
        'Api/V1/Accounting/FinancialCloseCockpitController.php' => 11,
        'Api/V1/Accounting/FiscalYearController.php' => 4,
        'Api/V1/Accounting/FxDerivativeController.php' => 2,
        'Api/V1/Accounting/InterCompanyTransferController.php' => 1,
        'Api/V1/Accounting/IntercompanyReconciliationController.php' => 3,
        'Api/V1/Accounting/JournalEntryController.php' => 1,
        'Api/V1/Accounting/LoanController.php' => 1,
        'Api/V1/Accounting/MultiCurrencyController.php' => 2,
        'Api/V1/Accounting/OverheadKeyController.php' => 6,
        'Api/V1/Accounting/ParallelLedgerController.php' => 3,
        'Api/V1/Accounting/ParkedDocumentController.php' => 2,
        'Api/V1/Accounting/PaymentFileController.php' => 6,
        'Api/V1/Accounting/PeriodLockController.php' => 1,
        'Api/V1/Accounting/PettyCashController.php' => 4,
        'Api/V1/Accounting/PostingValidationRuleController.php' => 2,
        'Api/V1/Accounting/ProfitCenterController.php' => 1,
        'Api/V1/Accounting/ProfitabilitySegmentController.php' => 3,
        'Api/V1/Accounting/ReportController.php' => 1,
        'Api/V1/Accounting/SpecialLedgerController.php' => 5,
        'Api/V1/Accounting/StatisticalKeyFigureController.php' => 4,
        'Api/V1/Accounting/TransferPricingController.php' => 6,
        'Api/V1/Accounting/TreasuryController.php' => 2,
        'Api/V1/Accounting/VarianceAnalysisController.php' => 3,
        'Api/V1/Accounting/XbrlController.php' => 3,
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
        'Api/V1/Core/UserController.php' => 2,
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
        'Api/V1/HR/AppraisalReviewController.php' => 5,
        'Api/V1/HR/BenefitsController.php' => 3,
        'Api/V1/HR/DepartmentController.php' => 2,
        'Api/V1/HR/DesignationController.php' => 2,
        'Api/V1/HR/EmployeeSelfServiceController.php' => 12,
        'Api/V1/HR/HRReportsController.php' => 1,
        'Api/V1/HR/LeaveAccrualController.php' => 3,
        'Api/V1/HR/LeavePolicyController.php' => 2,
        'Api/V1/HR/ManagerSelfServiceController.php' => 2,
        'Api/V1/HR/OvertimeController.php' => 1,
        'Api/V1/HR/PerformanceController.php' => 22,
        'Api/V1/HR/PublicHolidayController.php' => 3,
        'Api/V1/HR/RecruitmentController.php' => 18,
        'Api/V1/HR/ShiftPlanningController.php' => 10,
        'Api/V1/HR/SocialInsuranceController.php' => 5,
        'Api/V1/HR/SocialInsuranceExportController.php' => 1,
        'Api/V1/HR/SuccessionController.php' => 4,
        'Api/V1/HR/TimeEvaluationController.php' => 3,
        'Api/V1/HR/TrainingController.php' => 26,
        'Api/V1/HR/TravelExpenseController.php' => 9,
        'Api/V1/HR/TravelExpenseReportController.php' => 7,
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
        'Api/V1/Purchase/BillController.php' => 4,
        'Api/V1/Purchase/ContractController.php' => 1,
        'Api/V1/Purchase/ErsController.php' => 3,
        'Api/V1/Purchase/GoodsReceiptController.php' => 3,
        'Api/V1/Purchase/OutlineAgreementController.php' => 10,
        'Api/V1/Purchase/PaymentMadeController.php' => 5,
        'Api/V1/Purchase/PurchaseOrderController.php' => 2,
        'Api/V1/Purchase/PurchasingInfoRecordController.php' => 6,
        'Api/V1/Purchase/QuotaArrangementController.php' => 7,
        'Api/V1/Purchase/ReleaseStrategyController.php' => 2,
        'Api/V1/Purchase/RfqController.php' => 4,
        'Api/V1/Purchase/SchedulingAgreementController.php' => 9,
        'Api/V1/Purchase/ServiceEntrySheetController.php' => 6,
        'Api/V1/Purchase/SupplierPerformanceController.php' => 10,
        'Api/V1/Purchase/ThreeWayMatchController.php' => 2,
        'Api/V1/Purchase/VendorAdvanceController.php' => 3,
        'Api/V1/Purchase/VendorConsignmentController.php' => 4,
        'Api/V1/Purchase/VendorContractController.php' => 5,
        'Api/V1/Purchase/VendorCreditNoteController.php' => 1,
        'Api/V1/Purchase/VendorPricingController.php' => 4,
        'Api/V1/Purchase/VendorSourceListController.php' => 4,
        'Api/V1/RealEstate/VacancyController.php' => 4,
        'Api/V1/Reports/ExportController.php' => 1,
        'Api/V1/Reports/ReportsController.php' => 9,
        'Api/V1/Sales/BackdatedTransactionController.php' => 1,
        'Api/V1/Sales/BackorderController.php' => 4,
        'Api/V1/Sales/BillingPlanController.php' => 6,
        'Api/V1/Sales/BulkSaleController.php' => 1,
        'Api/V1/Sales/ConsignmentController.php' => 3,
        'Api/V1/Sales/ContactController.php' => 2,
        'Api/V1/Sales/CpqController.php' => 18,
        'Api/V1/Sales/CreditNoteController.php' => 1,
        'Api/V1/Sales/CustomerAdvanceController.php' => 1,
        'Api/V1/Sales/CustomerMaterialInfoController.php' => 3,
        'Api/V1/Sales/DeliverySplitController.php' => 2,
        'Api/V1/Sales/HandlingUnitController.php' => 6,
        'Api/V1/Sales/IntercompanySalesController.php' => 9,
        'Api/V1/Sales/InvoiceController.php' => 3,
        'Api/V1/Sales/OutputDeterminationController.php' => 4,
        'Api/V1/Sales/PaymentReceivedController.php' => 5,
        'Api/V1/Sales/PriceListController.php' => 7,
        'Api/V1/Sales/PriceOverrideController.php' => 2,
        'Api/V1/Sales/PricingConditionController.php' => 1,
        'Api/V1/Sales/ProductBundleController.php' => 1,
        'Api/V1/Sales/PromotionController.php' => 3,
        'Api/V1/Sales/QuickSaleTemplateController.php' => 1,
        'Api/V1/Sales/QuotationController.php' => 7,
        'Api/V1/Sales/RebateController.php' => 2,
        'Api/V1/Sales/RefundController.php' => 4,
        'Api/V1/Sales/RevenueRecognitionController.php' => 1,
        'Api/V1/Sales/SalesOrderController.php' => 7,
        'Api/V1/Sales/SalesOrderCostingController.php' => 4,
        'Api/V1/Sales/SalesReturnController.php' => 2,
        'Api/V1/Sales/SeasonalCampaignController.php' => 1,
        'Api/V1/Sales/ShipmentController.php' => 1,
        'Api/V1/Sales/ShippingRouteController.php' => 6,
        'Api/V1/Sales/ThirdPartyOrderController.php' => 6,
        'Api/V1/Sales/WalletController.php' => 7,
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
