<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reference data the schema is meaningless without.
 *
 * Consolidating the migrations dropped every DB::statement and DB::table call
 * on the grounds that they were data migrations, which is right for a backfill
 * — there are no rows to fix on a fresh database — and wrong for reference
 * data, which a fresh database needs precisely because it is fresh.
 *
 * These are the TDS/TCS sections the Indian tax services look up by code. The
 * SQLite schema comparison could not catch their absence: seeded rows are not
 * schema.
 *
 * Kept separate from the table definitions so it stays obvious that this file
 * inserts rows rather than declaring structure.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sections = [
            [
                'section_code'     => '194C',
                'description'      => 'Payment to Contractors and Sub-Contractors',
                'threshold_amount' => 30000.0000,
                'rate_individual'  => 1.00,
                'rate_company'     => 2.00,
                'rate_no_pan'      => 20.00,
                'is_active'        => true,
            ],
            [
                'section_code'     => '194I',
                'description'      => 'Rent',
                'threshold_amount' => 240000.0000,
                'rate_individual'  => 10.00,
                'rate_company'     => 10.00,
                'rate_no_pan'      => 20.00,
                'is_active'        => true,
            ],
            [
                'section_code'     => '194J',
                'description'      => 'Fees for Professional or Technical Services',
                'threshold_amount' => 30000.0000,
                'rate_individual'  => 10.00,
                'rate_company'     => 10.00,
                'rate_no_pan'      => 20.00,
                'is_active'        => true,
            ],
            [
                'section_code'     => '194H',
                'description'      => 'Commission or Brokerage',
                'threshold_amount' => 15000.0000,
                'rate_individual'  => 5.00,
                'rate_company'     => 5.00,
                'rate_no_pan'      => 20.00,
                'is_active'        => true,
            ],
            [
                'section_code'     => '194B',
                'description'      => 'Winnings from Lottery or Crossword Puzzle',
                'threshold_amount' => 10000.0000,
                'rate_individual'  => 30.00,
                'rate_company'     => 30.00,
                'rate_no_pan'      => 30.00,
                'is_active'        => true,
            ],
        ];

        DB::table('tds_sections')->insert(
            array_map(function (array $row): array {
                $now = now()->toDateTimeString();

                return array_merge($row, ['created_at' => $now, 'updated_at' => $now]);
            }, $sections)
        );

        $existing194Q = DB::table('tds_sections')->where('section_code', '194Q')->first();

        if (!$existing194Q) {
            DB::table('tds_sections')->insert([
                'section_code'     => '194Q',
                'description'      => 'TDS on Purchase of Goods (Finance Act 2021)',
                'threshold_amount' => 5_000_000.00,  // ₹50 lakh per FY
                'rate_individual'  => 0.10,
                'rate_company'     => 0.10,
                'rate_no_pan'      => 5.00,
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        $existing206C = DB::table('tds_sections')->where('section_code', '206C(1H)')->first();

        if (!$existing206C) {
            DB::table('tds_sections')->insert([
                'section_code'     => '206C(1H)',
                'description'      => 'TCS on Sale of Goods by Seller (effective 1 Oct 2020)',
                'threshold_amount' => 5_000_000.00,  // ₹50 lakh per FY
                'rate_individual'  => 0.10,
                'rate_company'     => 0.10,
                'rate_no_pan'      => 1.00,
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tds_sections')->whereIn('section_code', [
            '194C', '194I', '194J', '194H', '194B', '194Q', '206C(1H)',
        ])->delete();
    }
};
