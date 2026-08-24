<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the counter-based maintenance tables off the SAP "pm_" module prefix.
 *
 * The create migration already produces the new names, so this is a no-op on a
 * fresh database and only does work where the old names were created earlier.
 */
return new class extends Migration
{
    /** old name => new name */
    private const RENAMES = [
        'pm_counters'             => 'equipment_counters',
        'pm_counter_readings'     => 'counter_readings',
        'pm_maintenance_plans'    => 'counter_based_plans',
        'pm_orders'               => 'counter_based_orders',
        'pm_task_lists'           => 'maintenance_task_lists',
        'pm_task_list_operations' => 'task_list_operations',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $old => $new) {
            if (Schema::hasTable($old) && ! Schema::hasTable($new)) {
                Schema::rename($old, $new);
            }
        }

        if (Schema::hasTable('task_list_operations') && Schema::hasColumn('task_list_operations', 'pm_task_list_id')) {
            Schema::table('task_list_operations', function ($table): void {
                $table->renameColumn('pm_task_list_id', 'task_list_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('task_list_operations') && Schema::hasColumn('task_list_operations', 'task_list_id')) {
            Schema::table('task_list_operations', function ($table): void {
                $table->renameColumn('task_list_id', 'pm_task_list_id');
            });
        }

        foreach (array_reverse(self::RENAMES, true) as $old => $new) {
            if (Schema::hasTable($new) && ! Schema::hasTable($old)) {
                Schema::rename($new, $old);
            }
        }
    }
};
