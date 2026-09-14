<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Document\Document;
use App\Models\HR\TrainingCertification;
use App\Models\Manufacturing\WorkOrderOperation;
use Tests\TestCase;

/**
 * Carbon 3 signs diffIn*: $a->diffInX($b) is $b - $a. These methods measure
 * against now, so a reversed call turns every result negative.
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
}
