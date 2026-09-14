<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Services\Core\NumberGeneratorService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

/**
 * The first number of a sequence is taken like any other: from a row that
 * exists and is locked, so a request that created the sequence meanwhile is
 * counted instead of colliding with it.
 */
class NumberGeneratorFirstNumberTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_the_first_number_counts_a_sequence_another_request_just_started(): void
    {
        $this->setUpOrganization();
        $key = "{$this->organization->id}:INV:".date('Y');
        $interleaved = false;

        // Stands in for a second request that creates the sequence and takes
        // its first number between this request's first look at the table and
        // its write.
        DB::listen(function (QueryExecuted $query) use ($key, &$interleaved): void {
            if ($interleaved || ! str_contains($query->sql, 'number_sequences')) {
                return;
            }

            $interleaved = true;

            DB::table('number_sequences')->insertOrIgnore([
                'organization_id' => $this->organization->id,
                'sequence_key' => $key,
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('number_sequences')->where('sequence_key', $key)->increment('current_value');
        });

        $number = app(NumberGeneratorService::class)->generate('INV', '{prefix}-{number}', $this->organization->id);

        $this->assertSame('INV-00002', $number);
    }
}
