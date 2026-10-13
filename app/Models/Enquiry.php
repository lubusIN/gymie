<?php

namespace App\Models;

use App\Enums\EnquiryActivityType;
use App\Enums\Status;
use App\Models\Concerns\CascadesSoftDeletes;
use Database\Factories\EnquiryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $email
 * @property string|null $contact
 * @property Carbon|null $date
 * @property string|null $gender
 * @property Carbon|null $dob
 * @property Status|null $status
 * @property string|null $address
 * @property string|null $country
 * @property string|null $city
 * @property string|null $state
 * @property string|null $pincode
 * @property array<int, mixed>|null $interested_in
 * @property string|null $source
 * @property string|null $goal
 * @property Carbon|null $start_by
 * @property string|null $lost_reason
 * @property Carbon|null $converted_at
 * @property-read User|null $user
 * @property-read Member|null $member
 * @property-read FollowUp|null $nextFollowUp
 * @property-read Collection<int, FollowUp> $followUps
 * @property-read Collection<int, Activity> $activitiesAsSubject
 */
class Enquiry extends Model
{
    /** @use HasFactory<EnquiryFactory> */
    use CascadesSoftDeletes, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'contact',
        'date',
        'gender',
        'dob',
        'status',
        'address',
        'country',
        'city',
        'state',
        'pincode',
        'interested_in',
        'source',
        'goal',
        'start_by',
        'lost_reason',
        'converted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interested_in' => 'array',
            'date' => 'date',
            'dob' => 'date',
            'start_by' => 'date',
            'converted_at' => 'datetime',
            'status' => Status::class,
        ];
    }

    /**
     * Record the meaningful enquiry events that form the activity timeline.
     */
    protected static function booted(): void
    {
        static::created(function (self $enquiry): void {
            $enquiry->recordActivity(EnquiryActivityType::LeadCreated);
        });

        static::updated(function (self $enquiry): void {
            if ($enquiry->wasChanged('user_id')) {
                $enquiry->recordActivity(EnquiryActivityType::LeadOwnerChanged, [
                    'old' => self::userName($enquiry->getRawOriginal('user_id')),
                    'new' => self::userName($enquiry->user_id),
                ]);
            }

            if ($enquiry->wasChanged('source')) {
                $enquiry->recordActivity(EnquiryActivityType::LeadSourceChanged, [
                    'old' => $enquiry->getRawOriginal('source'),
                    'new' => $enquiry->source,
                ]);
            }

            if (! $enquiry->wasChanged('status')) {
                return;
            }

            if ($enquiry->status === Status::Member) {
                $enquiry->recordActivity(EnquiryActivityType::ConvertedToMember, [
                    'member' => $enquiry->member()->value('name'),
                ]);
            }

            if ($enquiry->status === Status::Lost) {
                $enquiry->recordActivity(EnquiryActivityType::MarkedLost, [
                    'reason' => $enquiry->lost_reason,
                ]);
            }
        });
    }

    /**
     * Get the follow-ups for the enquiry.
     *
     * @return HasMany<FollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    /**
     * Get the earliest pending follow-up for the enquiry.
     *
     * @return HasOne<FollowUp, $this>
     */
    public function nextFollowUp(): HasOne
    {
        return $this->hasOne(FollowUp::class)->ofMany(
            ['schedule_date' => 'min', 'id' => 'min'],
            fn ($query) => $query->where('status', Status::Pending->value),
        );
    }

    /**
     * Get the member created from this enquiry.
     *
     * @return HasOne<Member, $this>
     */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /**
     * Get the user for the enquiry.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the activity recorded for this enquiry.
     *
     * @return MorphMany<Activity, $this>
     */
    public function activitiesAsSubject(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * Append a business event to the enquiry timeline.
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordActivity(EnquiryActivityType $event, array $properties = []): void
    {
        activity(EnquiryActivityType::LogName)
            ->performedOn($this)
            ->event($event->value)
            ->withProperties($properties)
            ->log($event->value);
    }

    /**
     * Keep the enquiry pipeline aligned with its pending follow-ups.
     */
    public function synchronizePipelineStatus(): void
    {
        if (in_array($this->status, [Status::Member, Status::Lost], true)) {
            return;
        }

        $hasPendingFollowUp = $this->followUps()
            ->where('status', Status::Pending->value)
            ->exists();

        if ($hasPendingFollowUp) {
            $this->updateQuietly(['status' => Status::FollowUpScheduled]);

            return;
        }

        if ($this->status !== Status::Lead) {
            $this->updateQuietly(['status' => Status::Lead]);
        }
    }

    /**
     * Link the converted member and close the enquiry pipeline.
     */
    public function convertTo(Member $member): void
    {
        DB::transaction(function () use ($member): void {
            $this->refresh();

            if (in_array($this->status, [Status::Member, Status::Lost], true)) {
                throw new LogicException('Only open enquiries can be converted.');
            }

            $member->enquiry()->associate($this);
            $member->save();

            $this->update([
                'status' => Status::Member,
                'lost_reason' => null,
                'converted_at' => now(),
            ]);
        });
    }

    /**
     * Relationship method names to cascade when deleting/restoring.
     *
     * @return list<string>
     */
    protected static function relationsToCascade(): array
    {
        return ['followUps'];
    }

    /**
     * Resolve a lead owner name while retaining names for soft-deleted users.
     */
    private static function userName(mixed $userId): ?string
    {
        if (! is_numeric($userId)) {
            return null;
        }

        return User::withTrashed()->find((int) $userId)?->name;
    }
}
