<?php

namespace App\Services\Communication;

use App\Contracts\SettingsRepository;
use App\Enums\CommunicationTemplateKey;
use App\Models\CommunicationAutomation;
use App\Models\CommunicationTemplate;
use Illuminate\Validation\ValidationException;

final class CommunicationAutomationService
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly EmailDeliveryContext $emailContext,
    ) {}

    /** Return the configured template, falling back to the protected system default. */
    public function templateFor(CommunicationTemplateKey $event): CommunicationTemplate
    {
        $template = CommunicationAutomation::query()
            ->with('template')
            ->where('event', $event->value)
            ->first()
            ?->template;

        return $template ?? CommunicationTemplate::query()
            ->where('key', $event->value)
            ->firstOrFail();
    }

    /** Determine whether an automatic event is enabled by both global and event settings. */
    public function shouldSend(CommunicationTemplateKey $event): bool
    {
        if (! $event->isAutomatic()) {
            return false;
        }

        $settings = $this->settingsRepository->get();

        if (! (bool) data_get($settings, 'notifications.email.enabled', false)) {
            return false;
        }

        return (bool) CommunicationAutomation::query()
            ->where('event', $event->value)
            ->value('is_enabled');
    }

    /** Select a template for its event while preserving the event's delivery setting. */
    public function useTemplate(CommunicationTemplate $template): void
    {
        $event = $this->eventFor($template);
        $configuration = CommunicationAutomation::query()->firstOrNew([
            'event' => $event,
        ]);

        $configuration->fill([
            'communication_template_id' => $template->getKey(),
            'is_enabled' => $event->isAutomatic() && (bool) $configuration->is_enabled,
        ])->save();
    }

    /** Enable or disable automatic delivery for the selected event template. */
    public function setAutomaticDelivery(CommunicationTemplate $template, bool $enabled): void
    {
        $event = $this->eventFor($template);

        if ($enabled && ! $this->emailContext->isDeliveryConfigured()) {
            throw ValidationException::withMessages([
                'send_automatically' => $this->emailContext->unavailableReason(),
            ]);
        }

        if (! $template->isUsedForEvent()) {
            throw ValidationException::withMessages([
                'send_automatically' => __('app.communication.validation.template_not_in_use'),
            ]);
        }

        CommunicationAutomation::query()->updateOrCreate(
            ['event' => $event],
            [
                'communication_template_id' => $template->getKey(),
                'is_enabled' => $event->isAutomatic() && $enabled,
            ],
        );
    }

    /** Return a valid event for a persisted communication template. */
    private function eventFor(CommunicationTemplate $template): CommunicationTemplateKey
    {
        $event = $template->eventKey();

        if ($event === null) {
            throw ValidationException::withMessages([
                'event' => __('validation.required', ['attribute' => __('app.communication.fields.event')]),
            ]);
        }

        return $event;
    }
}
