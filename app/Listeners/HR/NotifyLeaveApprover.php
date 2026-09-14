<?php

declare(strict_types=1);

namespace App\Listeners\HR;

use App\Events\HR\LeaveRequestSubmitted;
use App\Models\User;
use App\Notifications\HR\LeaveRequestSubmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyLeaveApprover implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(LeaveRequestSubmitted $event): void
    {
        $leaveRequest = $event->leaveRequest;
        $employee = $leaveRequest->employee;

        if (!$employee) {
            return;
        }

        // The department manager is a user; the reporting manager is another
        // employee, reached through that employee's user account. When both
        // resolve to the same user they are notified once.
        $approvers = collect([
            $employee->department?->manager,
            $employee->reportingManager?->user,
        ])
            ->filter(fn (?User $user) => $user !== null && $user->is_active)
            ->unique('id')
            ->values();

        // Fall back to HR managers when the employee has no active manager
        if ($approvers->isEmpty()) {
            $approvers = User::withoutGlobalScopes()->whereHas('roles', function ($query) {
                $query->whereIn('slug', ['hr-manager', 'admin']);
            })
                ->where('organization_id', $employee->organization_id)
                ->where('is_active', true)
                ->get();
        }

        if ($approvers->isEmpty()) {
            return;
        }

        Notification::send($approvers, new LeaveRequestSubmittedNotification($leaveRequest));
    }
}
