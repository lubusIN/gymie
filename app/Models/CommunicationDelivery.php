<?php

namespace App\Models;

use App\Enums\CommunicationDeliveryStatus;
use Database\Factories\CommunicationDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommunicationDelivery extends Model
{
    /** @use HasFactory<CommunicationDeliveryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'communication_template_id',
        'actor_id',
        'related_type',
        'related_id',
        'channel',
        'recipient',
        'subject',
        'status',
        'is_test',
        'queued_at',
        'sent_at',
        'failed_at',
        'failure_reason',
        'retry_payload',
    ];

    /** @var list<string> */
    protected $hidden = [
        'retry_payload',
    ];

    /** Determine whether this failed delivery contains enough context to retry safely. */
    public function canBeRetried(): bool
    {
        return $this->status === CommunicationDeliveryStatus::Failed
            && is_array($this->retry_payload)
            && filled($this->retry_payload['handler'] ?? null);
    }

    /** @return BelongsTo<CommunicationTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return MorphTo<Model, $this> */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommunicationDeliveryStatus::class,
            'is_test' => 'boolean',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'retry_payload' => 'encrypted:array',
        ];
    }
}
