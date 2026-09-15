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
