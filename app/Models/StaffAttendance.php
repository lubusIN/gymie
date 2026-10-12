<?php

namespace App\Models;

use App\Support\AppConfig;
use Database\Factories\StaffAttendanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $staff_id
 * @property int|null $checked_in_by_id
 * @property Carbon $visited_on
 * @property Carbon $checked_in_at
 * @property-read User $staff
 * @property-read User|null $checkedInBy
 */
class StaffAttendance extends Model
{
    /** @use HasFactory<StaffAttendanceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'staff_id',
        'checked_in_by_id',
        'checked_in_at',
    ];

    /**
     * Keep the business-day key synchronized and reject duplicate check-ins.
     */
    protected static function booted(): void
    {
        static::saving(function (self $attendance): void {
            $attendance->visited_on = $attendance->checked_in_at
                ->copy()
                ->timezone(AppConfig::timezone())
                ->toDateString();

            if (! $attendance->isDirty(['staff_id', 'checked_in_at'])) {
                return;
            }

            $alreadyRecorded = self::query()
                ->where('staff_id', $attendance->staff_id)
                ->where('visited_on', $attendance->visited_on)
                ->when(
                    $attendance->exists,
                    fn (Builder $query): Builder => $query->whereKeyNot($attendance->getKey()),
                )
                ->exists();

            if ($alreadyRecorded) {
                throw ValidationException::withMessages([
                    'checked_in_at' => __('app.validation.staff_attendance_already_recorded_today'),
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
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_id')->withTrashed();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'visited_on' => 'date',
            'checked_in_at' => 'datetime',
        ];
    }
}
