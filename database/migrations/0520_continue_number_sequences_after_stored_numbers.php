<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Starts NumberGeneratorService's counters for the current year after the
 * numbers documents already carry.
 *
 * The documents below already carry numbers in these formats.
 * NumberGeneratorService numbers them from a counter per organization,
 * sequence and year in number_sequences, and a counter starting at zero would
 * issue numbers that exist. For each organization, the counter
 * is set to the highest number already used this year, unless it is past it.
 */
return new class extends Migration
{
    /**
     * Each document's table and number column, the counter it is numbered
     * from, and the pattern of this year's numbers. {year} is the current
     * year in both; the pattern's "number" group is the sequence and its
     * optional "period" group is appended to the counter name.
     *
     * @var list<array{table: string, column: string, sequence: string, pattern: string}>
     */
    private const DOCUMENTS = [
        ['table' => 'maintenance_orders', 'column' => 'order_number', 'sequence' => 'MO', 'pattern' => '/^MO-{year}-(?<number>\d+)$/'],
        ['table' => 'warehouse_transfer_orders', 'column' => 'to_number', 'sequence' => 'warehouse_transfer_order', 'pattern' => '/^TO-{year}-(?<number>\d+)$/'],
        ['table' => 'calibration_orders', 'column' => 'order_number', 'sequence' => 'CAL', 'pattern' => '/^CAL-{year}-(?<number>\d+)$/'],
        ['table' => 'dispute_cases', 'column' => 'case_number', 'sequence' => 'DISP-{year}', 'pattern' => '/^DISP-{year}(?<period>\d{2})-(?<number>\d+)$/'],
        ['table' => 'travel_requests', 'column' => 'request_number', 'sequence' => 'TRV', 'pattern' => '/^TRV-{year}-(?<number>\d+)$/'],
        ['table' => 'travel_expense_claims', 'column' => 'claim_number', 'sequence' => 'TEC', 'pattern' => '/^TEC-{year}-(?<number>\d+)$/'],
        ['table' => 'expenses', 'column' => 'expense_number', 'sequence' => 'EXP', 'pattern' => '/^EXP-{year}-(?<number>\d+)$/'],
        ['table' => 'expense_reports', 'column' => 'report_number', 'sequence' => 'expense_report', 'pattern' => '/^ER-{year}-(?<number>\d+)$/'],
        ['table' => 'currency_revaluations', 'column' => 'revaluation_number', 'sequence' => 'REVAL', 'pattern' => '/^REVAL-{year}-(?<number>\d+)$/'],
        ['table' => 'loans', 'column' => 'loan_number', 'sequence' => 'LN', 'pattern' => '/^LN-{year}-(?<number>\d+)$/'],
    ];

    public function up(): void
    {
        $year = date('Y');

        foreach (self::DOCUMENTS as $document) {
            foreach ($this->highestNumbers($document, $year) as $key => [$organizationId, $number]) {
                $this->continueAfter($key, $organizationId, $number);
            }
        }
    }

    /**
     * Counters past the stored numbers stay valid for the numbering that came
     * before, so there is nothing to undo.
     */
    public function down(): void
    {
    }

    /**
     * The highest number used this year, per counter key.
     *
     * @param  array{table: string, column: string, sequence: string, pattern: string}  $document
     * @return array<string, array{int, int}>
     */
    private function highestNumbers(array $document, string $year): array
    {
        $pattern = str_replace('{year}', $year, $document['pattern']);
        $highest = [];

        DB::table($document['table'])
            ->select(['id', 'organization_id', $document['column'].' as document_number'])
            ->whereNotNull('organization_id')
            ->where($document['column'], 'like', "%{$year}%")
            ->orderBy('id')
            ->chunk(1000, function ($rows) use ($pattern, $document, $year, &$highest): void {
                foreach ($rows as $row) {
                    if (! preg_match($pattern, (string) $row->document_number, $match)) {
                        continue;
                    }

                    $sequence = str_replace('{year}', $year, $document['sequence']).($match['period'] ?? '');
                    $key = "{$row->organization_id}:{$sequence}:{$year}";
                    $number = (int) $match['number'];

                    if ($number > ($highest[$key][1] ?? 0)) {
                        $highest[$key] = [(int) $row->organization_id, $number];
                    }
                }
            });

        return $highest;
    }

    private function continueAfter(string $key, int $organizationId, int $number): void
    {
        $current = DB::table('number_sequences')->where('sequence_key', $key)->value('current_value');

        if ($current === null) {
            DB::table('number_sequences')->insert([
                'organization_id' => $organizationId,
                'sequence_key' => $key,
                'current_value' => $number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if ((int) $current < $number) {
            DB::table('number_sequences')->where('sequence_key', $key)->update([
                'current_value' => $number,
                'updated_at' => now(),
            ]);
        }
    }
};
