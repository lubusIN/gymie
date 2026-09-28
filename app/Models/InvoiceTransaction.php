<?php

namespace App\Models;

use App\Enums\SubscriptionActivityType;
use App\Observers\InvoiceTransactionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $invoice_id
 * @property string $type
 * @property string|null $amount
 * @property Carbon|null $occurred_at
 * @property string|null $payment_method
 * @property string|null $note
 * @property string|null $reference_id
 * @property int|null $created_by
 * @property-read Invoice|null $invoice
 */
#[ObservedBy(InvoiceTransactionObserver::class)]
class InvoiceTransaction extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'type',
        'amount',
        'occurred_at',
        'payment_method',
        'note',
        'reference_id',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Get the invoice that this transaction belongs to.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Append this payment or refund to the related subscription timeline.
     */
    public function recordSubscriptionActivity(): void
    {
        $event = match ($this->type) {
            'payment' => SubscriptionActivityType::PaymentRecorded,
            'refund' => SubscriptionActivityType::RefundRecorded,
            default => null,
        };

        if ($event === null) {
            return;
        }

        $this->loadMissing('invoice.subscription');

        $this->invoice?->subscription?->recordActivity($event, [
            'invoice' => $this->invoice->number,
            'amount' => (float) ($this->amount ?? 0),
            'payment_method' => $this->payment_method,
            'note' => $this->note,
        ]);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function (self $transaction): void {
            $transaction->recordSubscriptionActivity();
        });

        static::saved(function (self $transaction): void {
            if ($transaction->invoice instanceof Invoice) {
                $transaction->invoice->syncFromTransactions();
            }
        });

        static::deleted(function (self $transaction): void {
            if ($transaction->invoice instanceof Invoice) {
                $transaction->invoice->syncFromTransactions();
            }
        });
    }
}
