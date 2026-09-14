<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Support\SchemaDocument;
use Tests\TestCase;

/**
 * database/DATABASE_SCHEMA.md is generated from the migrations and must match
 * them, so a schema change without `php artisan schema:doc` fails here.
 */
class SchemaDocTest extends TestCase
{
    public function test_the_schema_doc_matches_the_migrations(): void
    {
        $expected = SchemaDocument::render(database_path('migrations'));
        $committed = str_replace("\r\n", "\n", (string) file_get_contents(database_path('DATABASE_SCHEMA.md')));

        $this->assertTrue(
            $expected === $committed,
            'database/DATABASE_SCHEMA.md does not match the migrations. Run php artisan schema:doc.'
        );
    }
}
