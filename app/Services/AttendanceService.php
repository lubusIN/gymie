<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\StaffAttendance;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    /**
     * Start a visit after validating the member and their access entitlement.
     *
     * @throws DomainException When the member cannot access the gym.
     * @throws ValidationException When today's attendance already exists.
     */
    public function checkIn(
        Member $member,
        ?User $recordedBy,
        Carbon $checkedInAt,
    ): Attendance {
        if ($member->status !== Status::Active) {
            throw new DomainException(__('app.validation.attendance_active_member'));
        }

        $subscription = $member->currentSubscriptionAt($checkedInAt);

        if ($subscription === null) {
            throw new DomainException(__('app.validation.attendance_current_membership_required'));
        }

        try {
            return Attendance::query()->create([
                'member_id' => $member->getKey(),
                'subscription_id' => $subscription->getKey(),
                'checked_in_by_id' => $recordedBy?->getKey(),
                'checked_in_at' => $checkedInAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'checked_in_at' => __('app.validation.attendance_already_recorded_today'),
            ]);
        }
    }

    /**
     * Record an active staff member's attendance for the business day.
     *
     * @throws DomainException When the staff member is inactive.
     * @throws ValidationException When today's attendance already exists.
     */
    public function checkInStaff(
        User $staff,
        User $recordedBy,
        Carbon $checkedInAt,
    ): StaffAttendance {
        if ($staff->status !== Status::Active) {
            throw new DomainException(__('app.validation.attendance_active_staff'));
        }

        try {
            return StaffAttendance::query()->create([
                'staff_id' => $staff->getKey(),
                'checked_in_by_id' => $recordedBy->getKey(),
                'checked_in_at' => $checkedInAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'checked_in_at' => __('app.validation.staff_attendance_already_recorded_today'),
            ]);
        }
    }
}
