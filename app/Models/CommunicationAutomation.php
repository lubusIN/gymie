<?php

namespace App\Models;

use App\Enums\CommunicationTemplateKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class CommunicationAutomation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'event',
        'communication_template_id',
        'is_enabled',
    ];

    /** @return BelongsTo<CommunicationTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'communication_template_id');
    }

    /** Keep each automation compatible with its selected template. */
    protected static function booted(): void
    {
        static::saving(function (self $automation): void {
            $template = CommunicationTemplate::query()->find($automation->communication_template_id);

            if ($template === null) {
                return;
            }

            if ($template->event !== $automation->event) {
                throw ValidationException::withMessages([
                    'communication_template_id' => __('app.communication.validation.incompatible_template'),
                ]);
            }

            if (! $automation->event->isAutomatic()) {
                $automation->is_enabled = false;
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event' => CommunicationTemplateKey::class,
            'is_enabled' => 'boolean',
        ];
    }
}
