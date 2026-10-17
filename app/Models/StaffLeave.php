<?php

namespace App\Models;

use App\Enums\StaffLeaveType;
use App\Enums\Status;
use Database\Factories\StaffLeaveFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $staff_id
 * @property StaffLeaveType $type
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $reason
 * @property Status $status
 * @property int|null $reviewed_by_id
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property int|null $cancelled_by_id
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property-read User $staff
 * @property-read User|null $reviewedBy
 * @property-read User|null $cancelledBy
 */
class StaffLeave extends Model
{
    /** @use HasFactory<StaffLeaveFactory> */
    use HasFactory;

    /** @var array<string, string> */
    protected $attributes = [
        'status' => 'pending',
    ];

    /** @var list<string> */
    protected $fillable = [
        'staff_id',
        'type',
        'start_date',
        'end_date',
        'reason',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $leave): void {
            if (! $leave->start_date || ! $leave->end_date || ! $leave->staff_id) {
                return;
            }

            if ($leave->end_date->isBefore($leave->start_date)) {
                throw ValidationException::withMessages([
                    'end_date' => __('app.validation.leave_end_date_after_start'),
                ]);
            }

            if (! $leave->isDirty(['staff_id', 'start_date', 'end_date'])) {
                return;
            }

            $overlapsExistingLeave = self::query()
                ->where('staff_id', $leave->staff_id)
                ->whereIn('status', [Status::Pending->value, Status::Approved->value])
                ->whereDate('start_date', '<=', $leave->end_date)
                ->whereDate('end_date', '>=', $leave->start_date)
                ->when(
                    $leave->exists,
                    fn (Builder $query): Builder => $query->whereKeyNot($leave->getKey()),
                )
                ->exists();

            if ($overlapsExistingLeave) {
                throw ValidationException::withMessages([
                    'start_date' => __('app.validation.staff_leave_overlaps'),
                ]);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id')->withTrashed();
    }

    public function durationInDays(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function isPending(): bool
    {
        return $this->status === Status::Pending;
    }

    public function canBeReviewedBy(User $reviewer): bool
    {
        return $this->isPending() && $this->staff_id !== $reviewer->getKey();
    }

    public function canBeCancelledBy(User $actor): bool
    {
        return ($this->isPending() && $this->staff_id === $actor->getKey())
            || $this->status === Status::Approved;
    }

    public function approve(User $reviewer): void
    {
        $this->ensureReviewableBy($reviewer);

        $this->forceFill([
            'status' => Status::Approved,
            'reviewed_by_id' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'review_note' => null,
        ])->save();
    }

    public function reject(User $reviewer, string $note): void
    {
        $this->ensureReviewableBy($reviewer);

        $this->forceFill([
            'status' => Status::Rejected,
            'reviewed_by_id' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();
    }

    public function cancel(User $actor, string $reason): void
    {
        if (! $this->canBeCancelledBy($actor)) {
            throw ValidationException::withMessages([
                'status' => __('app.validation.staff_leave_cannot_cancel'),
            ]);
        }

        $this->markAsCancelled($actor, $reason);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => StaffLeaveType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => Status::class,
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    private function markAsCancelled(User $actor, string $reason): void
    {
        $this->forceFill([
            'status' => Status::Cancelled,
            'cancelled_by_id' => $actor->getKey(),
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();
    }

    private function ensureReviewableBy(User $reviewer): void
    {
        if ($this->canBeReviewedBy($reviewer)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => $this->isPending()
                ? __('app.validation.staff_leave_cannot_review_own')
                : __('app.validation.staff_leave_already_reviewed'),
        ]);
    }
}
