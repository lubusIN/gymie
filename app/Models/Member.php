<?php

namespace App\Models;

use App\Enums\MemberActivityType;
use App\Enums\Status;
use App\Helpers\Helpers;
use App\Models\Concerns\CascadesSoftDeletes;
use App\Observers\MemberCommunicationObserver;
use Carbon\CarbonInterface;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int|null $enquiry_id
 * @property string|null $photo
 * @property string $code
 * @property string $name
 * @property string|null $email
 * @property bool $email_communication_enabled
 * @property string|null $contact
 * @property string|null $emergency_contact
 * @property string|null $health_issue
 * @property string|null $gender
 * @property Carbon|null $dob
 * @property string|null $address
 * @property string|null $country
 * @property string|null $state
 * @property string|null $city
 * @property string|null $pincode
 * @property string|null $source
 * @property string|null $goal
 * @property Status|null $status
 * @property-read Collection<int, Subscription> $subscriptions
 * @property-read Collection<int, Attendance> $attendances
 * @property-read Enquiry|null $enquiry
 * @property-read Collection<int, Activity> $activitiesAsSubject
 */
#[ObservedBy(MemberCommunicationObserver::class)]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use CascadesSoftDeletes, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'enquiry_id',
        'photo',
        'code',
        'name',
        'email',
        'email_communication_enabled',
        'contact',
        'emergency_contact',
        'health_issue',
        'gender',
        'dob',
        'address',
        'country',
        'state',
        'city',
        'pincode',
        'source',
        'goal',
        'status',
    ];

    /** @var list<string> */
    private const ACTIVITY_FIELDS = [
        'photo',
        'name',
        'email',
        'email_communication_enabled',
        'contact',
        'emergency_contact',
        'health_issue',
        'gender',
        'dob',
        'address',
        'country',
        'state',
        'city',
        'pincode',
        'source',
        'goal',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'email_communication_enabled' => 'boolean',
            'status' => Status::class,
        ];
    }

    /** Determine whether staff may send email communication to this member. */
    public function allowsEmailCommunication(): bool
    {
        return $this->email_communication_enabled;
    }

    /**
     * Get the subscriptions for the member.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Find the membership that permits access on the given date.
     */
    public function currentSubscriptionAt(CarbonInterface $date): ?Subscription
    {
        return $this->subscriptions()
            ->with('plan')
            ->currentAt($date)
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Get the member's attendance history.
     *
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get the enquiry that was converted into this member.
     *
     * @return BelongsTo<Enquiry, $this>
     */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    /**
     * Get the activity recorded for this member.
     *
     * @return MorphMany<Activity, $this>
     */
    public function activitiesAsSubject(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * Append a business event to the member timeline.
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordActivity(MemberActivityType $event, array $properties = []): void
    {
        activity(MemberActivityType::LogName)
            ->performedOn($this)
            ->event($event->value)
            ->withProperties($properties)
            ->log($event->value);
    }

    /**
     * Register member number synchronization hooks.
     */
    protected static function booted(): void
    {
        static::saving(function (self $member): void {
            if (! $member->code) {
                $member->code = Helpers::generateLastNumber('member', Member::class, null, 'code');
            }
            Helpers::updateLastNumber('member', $member->code);
        });

        static::created(function (self $member): void {
            $member->recordActivity(MemberActivityType::MemberCreated);
        });

        static::updated(function (self $member): void {
            if ($member->wasChanged('status')) {
                $member->recordActivity(MemberActivityType::MemberStatusChanged, [
                    'old' => $member->getRawOriginal('status'),
                    'new' => $member->status?->value,
                ]);
            }

            $changedFields = array_values(array_intersect(
                array_keys($member->getChanges()),
                self::ACTIVITY_FIELDS,
            ));

            if ($changedFields !== []) {
                $member->recordActivity(MemberActivityType::MemberUpdated, [
                    'fields' => $changedFields,
                ]);
            }
        });
    }

    /**
     * Relationship method names to cascade when deleting/restoring.
     *
     * @return list<string>
     */
    protected static function relationsToCascade(): array
    {
        return ['subscriptions'];
    }
}
