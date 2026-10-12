<?php

namespace App\Models;

use App\Enums\CommunicationTemplateKey;
use Database\Factories\CommunicationTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunicationTemplate extends Model
{
    /** @use HasFactory<CommunicationTemplateFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'key',
        'event',
        'name',
        'channel',
        'subject',
        'body',
    ];

    /**
     * Get the delivery attempts made with this template.
     *
     * @return HasMany<CommunicationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(CommunicationDelivery::class);
    }

    /**
     * Get the business events that currently use this template.
     *
     * @return HasMany<CommunicationAutomation, $this>
     */
    public function automations(): HasMany
    {
        return $this->hasMany(CommunicationAutomation::class);
    }

    /**
     * Get the delivery configuration for this template's event.
     *
     * @return HasOne<CommunicationAutomation, $this>
     */
    public function eventConfiguration(): HasOne
    {
        return $this->hasOne(CommunicationAutomation::class, 'event', 'event');
    }

    /**
     * Return the variables available to the template editor.
     *
     * @return list<string>
     */
    public function availableVariables(): array
    {
        return $this->eventKey()?->variables() ?? [];
    }

    /** Return the configured event, including the legacy system-template fallback. */
    public function eventKey(): ?CommunicationTemplateKey
    {
        return $this->event ?? $this->systemKey();
    }

    /** Determine whether this template is currently used for its event. */
    public function isUsedForEvent(): bool
    {
        $event = $this->eventKey();

        if ($event === null) {
            return false;
        }

        $configuration = $this->eventConfiguration;

        if ($configuration !== null) {
            return $configuration->communication_template_id === $this->getKey();
        }

        return $this->systemKey() === $event;
    }

    /** Determine whether automatic delivery is enabled for this template's event. */
    public function isAutomaticDeliveryEnabled(): bool
    {
        return $this->isUsedForEvent()
            && ($this->eventKey()?->isAutomatic() ?? false)
            && ($this->eventConfiguration?->is_enabled ?? false);
    }

    /**
     * Return the protected system key when this is a built-in template.
     */
    public function systemKey(): ?CommunicationTemplateKey
    {
        return CommunicationTemplateKey::tryFrom($this->key);
    }

    /**
     * Determine whether the template is maintained by the application.
     */
    public function isSystem(): bool
    {
        return $this->systemKey() !== null;
    }

    /** Determine whether the template can be removed without losing delivery history. */
    public function canBeDeleted(): bool
    {
        return ! $this->isSystem();
    }

    /**
     * Validate template placeholders before persistence.
     */
    protected static function booted(): void
    {
        static::creating(function (CommunicationTemplate $template): void {
            if (blank($template->key)) {
                $template->key = 'custom_'.Str::lower((string) Str::ulid());
            }
        });

        static::saving(function (CommunicationTemplate $template): void {
            $originalSystemKey = CommunicationTemplateKey::tryFrom((string) $template->getRawOriginal('key'));

            if ($template->exists && $originalSystemKey !== null) {
                $template->key = $originalSystemKey->value;
                $template->name = (string) $template->getRawOriginal('name');
                $template->channel = (string) $template->getRawOriginal('channel');
                $template->event = $originalSystemKey;
            }

            if ($template->exists && $template->isDirty('event') && $template->automations()->exists()) {
                throw ValidationException::withMessages([
                    'event' => __('app.communication.validation.event_in_use'),
                ]);
            }

            $allowedVariables = $template->availableVariables();

            foreach (['subject', 'body'] as $field) {
                preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/i', (string) $template->{$field}, $matches);
                $unsupportedVariables = array_values(array_diff(array_unique($matches[1]), $allowedVariables));

                if ($unsupportedVariables !== []) {
                    throw ValidationException::withMessages([
                        $field => __('app.communication.validation.unsupported_variables', [
                            'variables' => implode(', ', $unsupportedVariables),
                        ]),
                    ]);
                }
            }
        });

        static::deleting(function (CommunicationTemplate $template): void {
            foreach ($template->automations as $automation) {
                $defaultTemplateId = self::query()
                    ->where('key', $automation->event->value)
                    ->value('id');

                if ($defaultTemplateId !== null) {
                    $automation->update(['communication_template_id' => $defaultTemplateId]);
                }
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => CommunicationTemplateKey::class,
        ];
    }
}
