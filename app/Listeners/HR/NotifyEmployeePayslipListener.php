<?php

declare(strict_types=1);

namespace App\Listeners\HR;

use App\Events\HR\PayslipGenerated;
use App\Models\User;
use App\Services\Core\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyEmployeePayslipListener implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(protected NotificationService $notificationService) {}

    public function handle(PayslipGenerated $event): void
    {
        $payslip = $event->payslip;
        $employee = $payslip->employee;

        if (!$employee?->user_id) {
            return;
        }

        $user = User::find($employee->user_id);
        if (!$user) {
            return;
        }

        $periodName = $payslip->payrollPeriod?->name;
        $message = $periodName
            ? "Your payslip for {$periodName} is now available"
            : 'Your payslip is now available';

        $this->notificationService->send(
            $user,
            'payslip_generated',
            $message,
            $message,
            null,
            null,
            null,
            [
                'payslip_id' => $payslip->id,
                'period_label' => $periodName,
                'net_salary' => $payslip->net_salary,
            ]
        );
    }
}
