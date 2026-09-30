<?php

namespace App\Models;

use App\Support\AppConfig;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $member_id
 * @property int|null $subscription_id
 * @property int|null $checked_in_by_id
 * @property Carbon $visited_on
 * @property Carbon $checked_in_at
 * @property-read Member $member
 * @property-read Subscription|null $subscription
 * @property-read User|null $checkedInBy
 */
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'member_id',
        'subscription_id',
        'checked_in_by_id',
        'checked_in_at',
    ];

    /**
     * Keep the business-day attendance key synchronized for every write path.
     */
    protected static function booted(): void
    {
        static::saving(function (self $attendance): void {
            $attendance->visited_on = $attendance->checked_in_at
                ->copy()
                ->timezone(AppConfig::timezone())
                ->toDateString();

            if (! $attendance->isDirty(['member_id', 'checked_in_at'])) {
                return;
            }

            $alreadyRecorded = self::query()
                ->where('member_id', $attendance->member_id)
                ->where('visited_on', $attendance->visited_on)
                ->when(
                    $attendance->exists,
                    fn (Builder $query): Builder => $query->whereKeyNot($attendance->getKey()),
                )
                ->exists();

            if ($alreadyRecorded) {
                throw ValidationException::withMessages([
                    'checked_in_at' => __('app.validation.attendance_already_recorded_today'),
                ]);
            }
        });
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<User, $this> */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by_id');
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
