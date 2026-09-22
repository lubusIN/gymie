<?php

namespace App\Models;

use App\Enums\Status;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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
 */
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
