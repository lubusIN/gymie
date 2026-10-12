<?php

namespace App\Services\Communication;

use App\Contracts\SettingsRepository;
use App\Support\AppConfig;
use App\Support\Data;
use LogicException;

final class EmailDeliveryContext
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
    ) {}

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return $this->settingsRepository->get();
    }

    /** Determine whether the selected mailer can deliver outside the application. */
    public function isDeliveryConfigured(): bool
    {
        $mailer = config('mail.default');

        if (! is_string($mailer) || $mailer === '') {
            return false;
        }

        $transport = config("mail.mailers.{$mailer}.transport");

        return is_string($transport)
            && ! in_array($transport, ['array', 'log'], true)
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Return the user-facing reason that outbound delivery is unavailable. */
    public function unavailableReason(): string
    {
        return __('app.notifications.mail_not_configured');
    }

    /** Return the deployment-owned sender address. */
    public function fromAddress(): string
    {
        $fromAddress = config('mail.from.address');

        if (! is_string($fromAddress) || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            throw new LogicException('The outbound mail sender address is invalid.');
        }

        return $fromAddress;
    }

    /**
     * Derive the business identity used in outgoing messages.
     *
     * @param  array<string, mixed>  $settings
     * @return array{name: string, email: string, contact: string}
     */
    public function identity(array $settings): array
    {
        $name = Data::string(data_get($settings, 'general.gym_name', AppConfig::string('app.name')));

        return [
            'name' => $name !== '' ? $name : 'Gymie',
            'email' => Data::string(data_get($settings, 'general.gym_email', '')),
            'contact' => Data::string(data_get($settings, 'general.gym_contact', '')),
        ];
    }

    /**
     * Render a message using the configured application locale.
     *
     * @template T
     *
     * @param  array<string, mixed>  $settings
     * @param  callable(): T  $callback
     * @return T
     */
    public function withLocale(array $settings, callable $callback): mixed
    {
        $originalLocale = app()->getLocale();
        $desiredLocale = Data::string(data_get($settings, 'general.locale', $originalLocale), $originalLocale);

        if ($desiredLocale !== '' && in_array($desiredLocale, AppConfig::supportedLocales(), true)) {
            app()->setLocale($desiredLocale);
        }

        try {
            return $callback();
        } finally {
            app()->setLocale($originalLocale);
        }
    }
}
