<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Support\Carbon;

/**
 * Date expressions every driver understands.
 *
 * Comparing the column against cut-off dates keeps the expression to standard
 * SQL. TIMESTAMPDIFF, DATEDIFF and CURDATE are MySQL's alone, and a query
 * written with them cannot run anywhere else — including the test database.
 */
trait PortableDates
{
    /**
     * A CASE labelling $column by age in years, with one placeholder per bound.
     *
     * @param  array<int, string>  $labels  exclusive year bound => label, ascending
     */
    protected function yearsBracket(string $column, array $labels, string $else): string
    {
        $whens = '';

        foreach ($labels as $label) {
            $whens .= "WHEN {$column} > ? THEN '{$label}' ";
        }

        return "CASE {$whens}ELSE '{$else}' END";
    }

    /**
     * The cut-off date for each bound, to bind to the CASE above.
     *
     * @param  list<int>  $bounds
     * @return list<string>
     */
    protected function bracketDates(array $bounds, ?string $asOf = null): array
    {
        $from = $asOf ? Carbon::parse($asOf) : now();

        return array_map(fn (int $years) => $from->copy()->subYears($years)->toDateString(), $bounds);
    }

    /**
     * Rows whose $column falls within the next $days, ignoring the year.
     *
     * SUBSTR of a date gives 'MM-DD' on every driver.
     */
    protected function withinNextDays($query, string $column, Carbon $today, int $days)
    {
        if ($days >= 366) {
            return $query;
        }

        $expr = "SUBSTR({$column}, 6, 5)";
        $from = $today->format('m-d');
        $to = $today->copy()->addDays($days)->format('m-d');

        return $from <= $to
            ? $query->whereRaw("{$expr} BETWEEN ? AND ?", [$from, $to])
            : $query->whereRaw("({$expr} >= ? OR {$expr} <= ?)", [$from, $to]);
    }

    /**
     * Rows whose $column falls on today's month and day, in any year.
     */
    protected function onMonthDay($query, string $column, Carbon $today)
    {
        return $query->whereRaw("SUBSTR({$column}, 6, 5) = ?", [$today->format('m-d')]);
    }

    /**
     * The next time a date's month and day comes round.
     */
    protected function nextOccurrence(Carbon $date, ?Carbon $from = null): Carbon
    {
        $from = ($from ?? now())->copy()->startOfDay();
        $next = $date->copy()->setYear($from->year)->startOfDay();

        return $next->lt($from) ? $next->addYear() : $next;
    }
}
