<?php

namespace App\Filament\Pages;

use App\Contracts\SettingsRepository;
use App\Filament\Schemas\SettingsForm;
use App\Services\Email\InvoiceEmailService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * @property-read Schema $form
 */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    /** @var string|null Page title */
    protected static ?string $title = null;

    /** @var string View file for the settings page */
    protected string $view = 'filament.pages.settings';

    /** @var array<string, mixed>|null Stores the settings data */
    public ?array $data = [];

    /**
     * Mount the page and load settings from the storage.
     */
    public function mount(SettingsRepository $settingsRepository): void
    {
        $settings = $settingsRepository->get();
        $this->data = $settings;
        $this->form->fill($settings);
    }

    public function getTitle(): string
    {
        return __('app.settings.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.settings.title');
    }

    /**
     * Configures a form instance by setting its schema and state path.
     *
     * @param  Schema  $schema  The form instance to configure.
     * @return Schema The configured form instance.
     */
    public function form(Schema $schema): Schema
    {
        return SettingsForm::configure($schema)
            ->statePath('data');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestEmail')
                ->label(__('app.settings.actions.send_test_email'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->modalDescription(__('app.settings.hints.test_email'))
                ->modalWidth(Width::Small)
                ->schema([
                    TextInput::make('recipient')
                        ->label(__('app.settings.fields.test_email_recipient'))
                        ->default(function (): ?string {
                            $email = data_get($this->data, 'general.gym_email');

                            return is_string($email) && $email !== '' ? $email : null;
                        })
                        ->email()
                        ->required(),
                ])
                ->action(function (array $data, InvoiceEmailService $emailService): void {
                    $recipient = is_string($data['recipient'] ?? null) ? $data['recipient'] : '';

                    if (! $emailService->isMailDeliveryConfigured()) {
                        Notification::make()
                            ->title(__('app.notifications.failed'))
                            ->body(__('app.notifications.mail_not_configured'))
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $emailService->sendTestEmail($recipient);
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title(__('app.notifications.failed'))
                            ->body(__('app.notifications.test_email_failed'))
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('app.notifications.test_email_sent'))
                        ->body(__('app.notifications.test_email_sent_body', ['email' => $recipient]))
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Persist the current settings.
     */
    public function save(SettingsRepository $settingsRepository): void
    {
        $settings = $this->form->getState();
        $general = is_array($settings['general'] ?? null) ? $settings['general'] : [];

        if (! empty($general['financial_year_start']) && is_string($general['financial_year_start'])) {
            $start = Carbon::parse($general['financial_year_start'])
                ->startOfMonth();

            $general['financial_year_start'] = $start->toDateString();
            $general['financial_year_end'] = $start->copy()->addYear()->subDay()->toDateString();
        }

        if (! empty($general['financial_year_end']) && is_string($general['financial_year_end'])) {
            $general['financial_year_end'] =
                Carbon::parse($general['financial_year_end'])
                    ->toDateString();
        }

        $gymLogo = $general['gym_logo'] ?? null;
        if (is_array($gymLogo)) {
            $general['gym_logo'] = $gymLogo[0] ?? null;
        }

        $settings['general'] = $general;

        try {
            $settingsRepository->put($settings);
            $this->form->fill($settings);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('app.notifications.failed'))
                ->body(__('app.notifications.failed_settings_save'))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('app.notifications.success'))
            ->body(__('app.notifications.success_settings_save'))
            ->success()
            ->send();
    }
}
