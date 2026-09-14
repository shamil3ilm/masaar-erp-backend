<?php

declare(strict_types=1);

namespace Database\Factories\Reports;

use App\Models\Core\Organization;
use App\Models\Reports\ReportExecution;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportExecutionFactory extends Factory
{
    protected $model = ReportExecution::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'saved_report_id' => null,
            'user_id' => null,
            'report_type' => fake()->randomElement(['profit_loss', 'balance_sheet', 'trial_balance', 'sales_summary']),
            'parameters' => ['start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->toDateString()],
            'format' => fake()->randomElement(['pdf', 'xlsx', 'csv', 'json']),
            'trigger' => ReportExecution::TRIGGER_MANUAL,
            'status' => ReportExecution::STATUS_COMPLETED,
            'file_path' => null,
            'file_size' => fake()->optional(0.5)->numberBetween(1024, 5242880),
            'row_count' => fake()->optional(0.5)->numberBetween(10, 10000),
            'execution_time_ms' => fake()->optional(0.5)->numberBetween(100, 30000),
            'error_message' => null,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ];
    }
}
