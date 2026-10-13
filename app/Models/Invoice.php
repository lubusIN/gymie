<?php

namespace App\Models;

use App\Enums\Status;
use App\Enums\SubscriptionActivityType;
use App\Helpers\Helpers;
use App\Observers\InvoiceObserver;
use App\Support\AppConfig;
use App\Support\Billing\InvoiceCalculator;
use Carbon\Carbon;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string|null $number
 * @property int|null $subscription_id
 * @property Carbon|null $date
 * @property Carbon|null $due_date
 * @property string|null $payment_method
 * @property Status|null $status
 * @property string|null $tax
 * @property string|null $discount
 * @property string|null $discount_amount
 * @property string|null $discount_note
 * @property string|null $paid_amount
 * @property string|null $total_amount
 * @property string|null $due_amount
 * @property string|null $subscription_fee
 * @property-read Subscription|null $subscription
 * @property-read Collection<int, InvoiceTransaction> $transactions
 */
#[ObservedBy(InvoiceObserver::class)]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $observables = ['becameOverdue'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'subscription_id',
        'date',
        'due_date',
        'payment_method',
        'status',
        'tax',
        'discount',
        'discount_amount',
        'discount_note',
        'paid_amount',
        'total_amount',
        'due_amount',
        'subscription_fee',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'due_date' => 'date',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'subscription_fee' => 'decimal:2',
            'status' => Status::class,
        ];
    }

    /**
     * The subscription this invoice is for.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get a human-friendly label for the invoice status.
     *
     * This is useful for tables and UI elements where you want a consistent
     * display label regardless of whether the attribute is currently cast.
     */
    public function getDisplayStatusLabel(): string
    {
        return $this->status?->getLabel() ?? '';
    }

    /**
     * Get the transactions for the invoice.
     *
     * @return HasMany<InvoiceTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(InvoiceTransaction::class);
    }

    /**
     * Sync the invoice's paid_amount, due_amount, and status based on its transactions.
     */
    public function syncFromTransactions(): void
    {
        $previousStatus = $this->status?->value;
        $paymentsTotal = (float) $this->transactions()
            ->where('type', 'payment')
            ->sum('amount');

        $refundsTotal = (float) $this->transactions()
            ->where('type', 'refund')
            ->sum('amount');
        $ledger = InvoiceCalculator::ledger(
            totalAmount: (float) ($this->total_amount ?? 0),
            paymentsTotal: $paymentsTotal,
            refundsTotal: $refundsTotal,
            currentStatus: $previousStatus,
            isPastDue: $this->due_date !== null
                && $this->due_date->lt(Carbon::today(AppConfig::timezone())),
        );

        $this->newQuery()
            ->whereKey($this->getKey())
            ->update([
                'paid_amount' => $ledger['paid'],
                'due_amount' => $ledger['due'],
                'status' => $ledger['status'],
            ]);

        $this->refresh();

        $currentStatus = $this->status?->value;

        if ($previousStatus !== null && $previousStatus !== $currentStatus) {
            $this->recordSubscriptionActivity(SubscriptionActivityType::InvoiceStatusChanged, [
                'old' => $previousStatus,
                'new' => $currentStatus,
            ]);

            if ($currentStatus === Status::Overdue->value) {
                $this->fireModelEvent('becameOverdue', false);
            }
        }
    }

    /**
     * Register invoice calculations and transaction synchronization hooks.
     */
    protected static function booted(): void
    {
        static::saving(fn (self $invoice) => $invoice->prepareForSaving());
        static::created(fn (self $invoice) => $invoice->recordCreation());
        static::updated(fn (self $invoice) => $invoice->recordUpdate());
    }

    /** Normalize generated identifiers and financial totals before persistence. */
    private function prepareForSaving(): void
    {
        if (! $this->number) {
            $this->number = Helpers::generateLastNumber('invoice', self::class, $this->date);
        }

        Helpers::updateLastNumber('invoice', $this->number, $this->date);

        $summary = InvoiceCalculator::summary(
            fee: (float) ($this->subscription_fee ?? 0),
            taxRatePercent: Helpers::getTaxRate(),
            discountAmount: (float) ($this->discount_amount ?? 0),
            paidAmount: (float) ($this->paid_amount ?? 0),
        );

        $this->subscription_fee = $summary['fee'];
        $this->discount_amount = $summary['discount_amount'];
        $this->tax = $summary['tax'];
        $this->total_amount = $summary['total'];
        $this->paid_amount = $summary['paid'];
    }

    /** Record the invoice and convert an initial paid amount into a ledger entry. */
    private function recordCreation(): void
    {
        $this->recordSubscriptionActivity(SubscriptionActivityType::InvoiceCreated, [
            'amount' => (float) ($this->total_amount ?? 0),
            'status' => $this->status?->value,
        ]);

        $this->recordInitialPayment();
        $this->syncFromTransactions();
    }

    /** Record a status transition before reconciling the ledger again. */
    private function recordUpdate(): void
    {
        if ($this->wasChanged('status')) {
            $this->recordSubscriptionActivity(SubscriptionActivityType::InvoiceStatusChanged, [
                'old' => $this->getRawOriginal('status'),
                'new' => $this->status?->value,
            ]);

            if ($this->status === Status::Overdue) {
                $this->fireModelEvent('becameOverdue', false);
            }
        }

        $this->syncFromTransactions();
    }

    /** Persist the initial payment supplied with a newly created invoice. */
    private function recordInitialPayment(): void
    {
        $paidAmount = min(
            max((float) ($this->paid_amount ?? 0), 0),
            (float) ($this->total_amount ?? 0),
        );

        if ($paidAmount <= 0) {
            return;
        }

        $transaction = new InvoiceTransaction([
            'type' => 'payment',
            'amount' => $paidAmount,
            'occurred_at' => now()->timezone(AppConfig::timezone()),
            'payment_method' => $this->payment_method,
            'note' => 'Initial payment',
            'created_by' => auth()->id(),
        ]);
        $gymId = $this->getAttribute('gym_id');

        if (filled($gymId)) {
            $transaction->setAttribute('gym_id', $gymId);
        }

        $transaction->invoice()->associate($this);
        $transaction->saveQuietly();
        $transaction->recordSubscriptionActivity();
    }

    /**
     * Append an invoice event to the related subscription timeline.
     *
     * @param  array<string, mixed>  $properties
     */
    private function recordSubscriptionActivity(SubscriptionActivityType $event, array $properties): void
    {
        $this->loadMissing('subscription');

        $this->subscription?->recordActivity($event, [
            'invoice' => $this->number,
            ...$properties,
        ]);
    }
}
