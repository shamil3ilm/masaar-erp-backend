<?php

declare(strict_types=1);

namespace App\Services\Core\Widgets;

use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Models\HR\LeaveRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard widgets that report on headcount, attendance, and leave.
 */
class HrWidgetProvider extends WidgetProvider
{
    public function getEmployeeSummary(array $config = []): array
    {
        $query = Employee::where('organization_id', $this->organizationId);

        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }

        $total = (clone $query)->count();
        $active = (clone $query)->where('employment_status', 'active')->count();
        $onProbation = (clone $query)->where('employment_status', 'probation')->count();
        $onNotice = (clone $query)->where('employment_status', 'notice')->count();

        return [
            'total' => $total,
            'active' => $active,
            'on_probation' => $onProbation,
            'on_notice' => $onNotice,
            'label' => 'Employee Summary',
        ];
    }

    public function getAttendanceToday(array $config = []): array
    {
        $today = Carbon::today()->toDateString();

        $query = Employee::where('organization_id', $this->organizationId)
            ->where('employment_status', 'active');

        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }

        $totalActive = $query->count();

        $attendanceQuery = Attendance::whereHas('employee', function ($q) {
            $q->where('organization_id', $this->organizationId);
            if ($this->branchId) {
                $q->where('branch_id', $this->branchId);
            }
        })->whereDate('attendance_date', $today);

        $present = (clone $attendanceQuery)->where('status', 'present')->count();
        $late = (clone $attendanceQuery)->where('is_late', true)->count();
        $onLeave = (clone $attendanceQuery)->whereIn('status', ['on_leave', 'half_day_leave'])->count();

        return [
            'total_employees' => $totalActive,
            'present' => $present,
            'late' => $late,
            'on_leave' => $onLeave,
            'attendance_rate' => $totalActive > 0 ? round(($present / $totalActive) * 100, 1) : 0,
            'label' => "Today's Attendance",
        ];
    }

    public function getPendingLeaveApprovals(array $config = []): array
    {
        $count = LeaveRequest::whereHas('employee', function ($q) {
            $q->where('organization_id', $this->organizationId);
            if ($this->branchId) {
                $q->where('branch_id', $this->branchId);
            }
        })->where('status', 'pending')->count();

        return [
            'value' => $count,
            'label' => 'Pending Leave Approvals',
            'severity' => $count > 5 ? 'warning' : 'normal',
        ];
    }

    public function getUpcomingBirthdays(array $config = []): array
    {
        $days = $config['days'] ?? 7;

        $query = Employee::where('organization_id', $this->organizationId)
            ->where('employment_status', 'active')
            ->whereNotNull('date_of_birth');

        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }

        // DB-level year-boundary-safe "days until next birthday".
        $birthdayRaw = "MOD(DATEDIFF(DATE_ADD(date_of_birth, INTERVAL (YEAR(CURDATE()) - YEAR(date_of_birth)) YEAR), CURDATE()) + 366, 366)";

        $birthdays = (clone $query)
            ->whereRaw("{$birthdayRaw} <= ?", [$days])
            ->selectRaw("CONCAT(first_name, ' ', last_name) as name, DATE_FORMAT(date_of_birth, '%b %d') as dob_fmt, ({$birthdayRaw}) as days_away")
            ->orderByRaw($birthdayRaw)
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'name'     => $r->name,
                'date'     => $r->dob_fmt,
                'is_today' => (int) $r->days_away === 0,
            ]);

        return [
            'items' => $birthdays->values()->toArray(),
            'label' => 'Upcoming Birthdays',
        ];
    }

    public function getHeadcountByDepartment(array $config = []): array
    {
        $query = Employee::where('organization_id', $this->organizationId)
            ->where('employment_status', 'active');

        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }

        $data = $query->select('department_id', DB::raw('count(*) as count'))
            ->with('department:id,name')
            ->groupBy('department_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'labels' => $data->map(fn($d) => $d->department->name ?? 'Unassigned')->toArray(),
            'data' => $data->pluck('count')->toArray(),
        ];
    }
}
