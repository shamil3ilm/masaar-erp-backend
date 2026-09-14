<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Accounting\FixedAsset;
use App\Models\Document\Document;
use App\Models\HR\EmployeeExperience;
use App\Models\HR\Shift;
use App\Models\HR\ShiftPattern;
use App\Models\HR\TrainingCertification;
use App\Models\HR\WorkSchedule;
use App\Models\Manufacturing\WorkOrderOperation;
use App\Models\RealEstate\VacancyPeriod;
use App\Models\Sales\BackdatedTransaction;
use DateTime;
use Tests\TestCase;

/**
 * Carbon 3 signs diffIn* and returns a float: $a->diffInX($b) is $b - $a.
 * A reversed call turns these results negative, and an uncast one throws
 * from an int method under strict types.
 */
class DateDiffTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-14 12:00:00');
    }

    public function test_a_document_expiring_later_than_the_window_is_not_expiring_soon(): void
    {
        $this->assertFalse((new Document)->forceFill(['expiry_date' => '2026-11-13'])->isExpiringSoon(30));
        $this->assertTrue((new Document)->forceFill(['expiry_date' => '2026-09-24'])->isExpiringSoon(30));
    }

    public function test_a_certification_expiring_later_than_the_window_is_not_expiring_soon(): void
    {
        $this->assertFalse((new TrainingCertification)->forceFill(['expiry_date' => '2026-11-13'])->isExpiringSoon(30));
        $this->assertTrue((new TrainingCertification)->forceFill(['expiry_date' => '2026-09-24'])->isExpiringSoon(30));
    }

    public function test_an_operation_in_progress_reports_the_minutes_since_it_started(): void
    {
        $operation = (new WorkOrderOperation)->forceFill([
            'status' => WorkOrderOperation::STATUS_IN_PROGRESS,
            'started_at' => '2026-09-14 10:30:00',
        ]);

        $this->assertSame(90, $operation->getCurrentDuration());
    }

    public function test_a_schedule_counts_late_and_early_leaving_minutes(): void
    {
        $schedule = (new WorkSchedule)->forceFill([
            'start_time' => '2026-09-14 08:00:00',
            'end_time' => '2026-09-14 17:00:00',
            'grace_period_minutes' => 10,
        ]);

        $this->assertSame(20, $schedule->getLateMinutes(new DateTime('2026-09-14 08:30:00')));
        $this->assertSame(45, $schedule->getEarlyLeavingMinutes(new DateTime('2026-09-14 16:15:00')));
    }

    public function test_an_overnight_shift_lasts_until_the_next_morning(): void
    {
        $pattern = (new ShiftPattern)->forceFill([
            'start_time' => '22:00',
            'end_time' => '06:00',
            'crosses_midnight' => true,
            'break_minutes' => 30,
        ]);
        $shift = (new Shift)->forceFill([
            'start_time' => '22:00',
            'end_time' => '06:00',
            'is_overnight' => true,
            'break_minutes' => 30,
        ]);

        $this->assertSame(450, $pattern->getDurationMinutes());
        $this->assertSame(450, $shift->getNetMinutesAttribute());
    }

    public function test_day_and_month_counts_are_whole_numbers(): void
    {
        $experience = (new EmployeeExperience)->forceFill(['from_date' => '2024-01-01', 'to_date' => '2026-07-01']);
        $vacancy = (new VacancyPeriod)->forceFill(['vacant_from' => '2026-09-04', 'vacant_to' => '2026-09-14']);
        $backdated = (new BackdatedTransaction)->forceFill(['transaction_date' => '2026-09-01', 'entry_date' => '2026-09-14']);

        $this->assertSame(30, $experience->getDurationInMonths());
        $this->assertSame(10, $vacancy->getDaysVacant());
        $this->assertSame(13, $backdated->getDaysDifference());
    }

    public function test_sum_of_years_digits_uses_the_months_already_depreciated(): void
    {
        // 5-year life, 12 of 60 months gone: 15000 * (48 / 60 / 15) = 800 a month.
        $asset = (new FixedAsset)->forceFill([
            'depreciation_method' => FixedAsset::DEPRECIATION_SUM_OF_YEARS_DIGITS,
            'acquisition_date' => '2024-09-14',
            'last_depreciation_date' => '2025-09-14',
            'acquisition_cost' => 16000,
            'salvage_value' => 1000,
            'useful_life_years' => 5,
            'book_value' => 12000,
        ]);

        $this->assertSame(800.0, $asset->calculatePeriodicDepreciation(1));
    }
}
