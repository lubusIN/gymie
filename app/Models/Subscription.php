<?php

namespace App\Models;

use App\Enums\MemberActivityType;
use App\Enums\Status;
use App\Enums\SubscriptionActivityType;
use App\Observers\SubscriptionCommunicationObserver;
use App\Support\AppConfig;
use Carbon\CarbonInterface;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int|null $renewed_from_subscription_id
 * @property int|null $member_id
 * @property int|null $plan_id
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property Status|null $status
 * @property-read Member|null $member
 * @property-read Plan|null $plan
 * @property-read Subscription|null $renewedFrom
 * @property-read Collection<int, Subscription> $renewals
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Invoice|null $invoice
 * @property-read Collection<int, Activity> $activitiesAsSubject
 */
#[ObservedBy(SubscriptionCommunicationObserver::class)]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'renewed_from_subscription_id',
        'member_id',
        'plan_id',
        'start_date',
        'end_date',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => Status::class,
        ];
    }

    /** Constrain subscriptions that grant access on the given business date. */
    #[Scope]
    protected function currentAt(Builder $query, CarbonInterface $date): Builder
    {
        return $query
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->whereIn('status', [Status::Ongoing->value, Status::Expiring->value]);
    }

    /**
     * Record subscription lifecycle changes on both relevant timelines.
     */
    protected static function booted(): void
    {
        static::created(function (self $subscription): void {
            $subscriptionEvent = $subscription->renewed_from_subscription_id
                ? SubscriptionActivityType::SubscriptionRenewed
                : SubscriptionActivityType::SubscriptionCreated;
            $memberEvent = $subscription->renewed_from_subscription_id
                ? MemberActivityType::SubscriptionRenewed
                : MemberActivityType::SubscriptionCreated;
            $properties = [
                'plan' => $subscription->plan?->name,
                'start_date' => $subscription->start_date?->toDateString(),
                'end_date' => $subscription->end_date?->toDateString(),
                'status' => $subscription->status?->value,
            ];

            $subscription->recordActivity($subscriptionEvent, $properties);
            $subscription->member?->recordActivity($memberEvent, $properties);
        });

        static::updated(function (self $subscription): void {
            if (! $subscription->wasChanged('status')) {
                return;
            }

            $properties = [
                'plan' => $subscription->plan?->name,
                'old' => $subscription->getRawOriginal('status'),
                'new' => $subscription->status?->value,
            ];

            $subscription->recordActivity(SubscriptionActivityType::SubscriptionStatusChanged, $properties);
            $subscription->member?->recordActivity(MemberActivityType::SubscriptionStatusChanged, $properties);
        });
    }

    /**
     * Get the activity recorded for this subscription.
     *
     * @return MorphMany<Activity, $this>
     */
    public function activitiesAsSubject(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * Append a business event to the subscription timeline.
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordActivity(SubscriptionActivityType $event, array $properties = []): void
    {
        activity(SubscriptionActivityType::LogName)
            ->performedOn($this)
            ->event($event->value)
            ->withProperties($properties)
            ->log($event->value);
    }

    /**
     * Get the invoices for the subscription.
     */
    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the invoice generated for this subscription.
     *
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Get the number of days remaining for a current subscription.
     */
    public function daysRemaining(): ?int
    {
        if (
            $this->start_date === null
            || $this->end_date === null
            || in_array($this->status, [Status::Expired, Status::Renewed], true)
        ) {
            return null;
        }

        $timezone = AppConfig::timezone();
        $today = Carbon::today($timezone);
        $startDate = Carbon::parse($this->start_date->toDateString(), $timezone)->startOfDay();
        $endDate = Carbon::parse($this->end_date->toDateString(), $timezone)->startOfDay();

        if ($startDate->isAfter($today) || $endDate->isBefore($today)) {
            return null;
        }

        $days = (int) $today->diffInDays($endDate, false);

        return max($days, 0);
    }

    /**
     * Get the subscription that this subscription was renewed from, if any.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_subscription_id');
    }

    /**
     * Get the subscriptions that were renewed from this subscription.
     *
     * @return HasMany<Subscription, $this>
     */
    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'renewed_from_subscription_id');
    }

    /**
     * The member who owns this subscription.
     */
    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The plan this subscription is for.
     */
    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
