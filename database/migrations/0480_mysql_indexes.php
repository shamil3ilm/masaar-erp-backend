<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indexes MySQL can express and the Blueprint cannot.
 *
 * Both index a prefix of a TEXT column, which needs the length in the index
 * definition — syntax Laravel's schema builder has no way to emit, so the
 * originals dropped to raw SQL behind a driver check.
 *
 * Worth noting how nearly these were lost: the consolidation was verified by
 * building the schema on SQLite and diffing it, and a MySQL-only statement is
 * invisible to that comparison. It is a reminder that the check only covers
 * what the check can see.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('CREATE INDEX attr_val_attr_id_value_text_idx ON product_attribute_values (attribute_id, value_text(191))');
        DB::statement('CREATE INDEX idx_job_class ON failed_jobs_monitor (job_class(191))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('DROP INDEX attr_val_attr_id_value_text_idx ON product_attribute_values');
        DB::statement('DROP INDEX idx_job_class ON failed_jobs_monitor');
    }
};
