<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\SchemaDocument;
use Illuminate\Console\Command;

class DocumentSchema extends Command
{
    protected $signature = 'schema:doc';

    protected $description = 'Write database/DATABASE_SCHEMA.md from the migrations';

    public function handle(): int
    {
        $path = database_path('DATABASE_SCHEMA.md');

        file_put_contents($path, SchemaDocument::render(database_path('migrations')));

        $this->info('Wrote ' . $path);

        return self::SUCCESS;
    }
}
