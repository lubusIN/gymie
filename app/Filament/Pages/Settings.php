<?php

namespace App\Filament\Pages;

use App\Contracts\SettingsRepository;
use App\Helpers\Helpers;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

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

    /** @var string|null Stores the uploaded settings file */
    public ?string $settings_file = null;

    /**
     * Mount the page and load settings from the storage.
     */
    public function mount(): void
    {
        $settings = Helpers::getSettings();
        $this->data = $settings;
        $general = is_array($this->data['general'] ?? null) ? $this->data['general'] : [];

        // Ensure gym_logo is always set correctly
        foreach (['gym_logo'] as $logoType) {
            if (! empty($general[$logoType]) && is_array($general[$logoType])) {
                $general[$logoType] = $general[$logoType];
            }
        }

        $this->data['general'] = $general;

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
     * Defines the form schema with multiple tabs.
     *
     * @return array<int, Component>
     */
    protected function getFormSchema(): array
    {
        return [
            Tabs::make(__('app.settings.title'))
                ->tabs([
                    $this->generalTab(),
                    $this->invoiceTab(),
                    $this->memberTab(),
                    $this->chargesTab(),
                    $this->expensesTab(),
                    $this->subscriptionsTab(),
                ]),
        ];
    }

    /**
     * General Tab Schema.
     */
    private function generalTab(): Tab
    {
        return Tab::make(__('app.settings.tabs.gym_info'))
            ->icon('heroicon-m-briefcase')
            ->schema([
                Section::make(__('app.settings.sections.general_information'))
                    ->aside()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('general.gym_name')
                                    ->label(__('app.settings.fields.gym_name')),
                                Select::make('general.currency')
                                    ->label(__('app.settings.fields.currency'))
                                    ->options(Helpers::getCurrencies())
                                    ->searchable(),
                                FileUpload::make('general.gym_logo')
                                    ->label(__('app.settings.fields.gym_logo'))
                                    ->disk('public')
                                    ->directory('images')
                                    ->imageEditor()
                                    ->deletable()
                                    ->visibility('public')
                                    ->image()
                                    ->acceptedFileTypes([
                                        'image/jpeg',
                                        'image/png',
                                        'image/webp',
                                    ])
                                    ->maxSize(2048)
                                    ->columnSpanFull(),
                                DatePicker::make('general.financial_year_start')
                                    ->native(false)
                                    ->label(__('app.settings.fields.financial_year_start'))
                                    ->helperText('Rounded to the first day of that month.')
                                    ->reactive()
                                    ->afterStateUpdated(function (?string $state, callable $set): void {
                                        if (filled($state)) {
                                            $start = Carbon::parse($state)
                                                ->startOfMonth();

                                            $set('general.financial_year_start', $start->toDateString());
                                            $set('general.financial_year_end', $start->copy()->addYear()->subDay()->toDateString());

                                            return;
                                        }

                                        $set('general.financial_year_end', null);
                                    }),
                                DatePicker::make('general.financial_year_end')
                                    ->native(false)
                                    ->label(__('app.settings.fields.financial_year_end'))
                                    ->helperText('Auto calculated based on the start month.')
                                    ->readOnly(),
                            ]),
                    ])
                    ->columnSpan(3),

                Section::make(__('app.settings.sections.address'))
                    ->aside()
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                Textarea::make('general.address')
                                    ->label(__('app.settings.fields.address')),
                            ]),
                        Grid::make(4)
                            ->schema([
                                Select::make('general.country')
                                    ->label(__('app.settings.fields.country'))
                                    ->options(Helpers::getCountries())
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(fn($state, callable $set) => [
                                        $set('general.state', null),
                                        $set('general.city', null),
                                    ]),
                                Select::make('general.state')
                                    ->label(__('app.settings.fields.state'))
                                    ->options(fn($get) => Helpers::getStates($get('general.country')))
                                    ->searchable()
                                    ->reactive(),
                                Select::make('general.city')
                                    ->label(__('app.settings.fields.city'))
                                    ->options(fn($get) => Helpers::getCities($get('general.state')))
                                    ->searchable()
                                    ->reactive(),
                                TextInput::make('general.zip')
                                    ->label(__('app.settings.fields.zip'))
                                    ->maxLength(10),
                            ]),
                    ])
                    ->columnSpan(3),
                Section::make(__('app.settings.sections.contact_information'))
                    ->aside()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('general.gym_email')
                                    ->label(__('app.settings.fields.email_address'))
                                    ->email()
                                    ->prefixIcon('heroicon-o-envelope'),
                                TextInput::make('general.gym_contact')
                                    ->tel()
                                    ->prefixIcon('heroicon-o-phone')
                                    ->label(__('app.settings.fields.contact_no')),
                            ]),
                    ])
                    ->columnSpan(3),
            ]);
    }

    /**
     * Invoice Tab Schema.
     */
    private function invoiceTab(): Tab
    {
        return
            Tab::make(__('app.settings.tabs.invoice'))->icon('heroicon-m-document-text')
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextInput::make('invoice.prefix')
                            ->placeholder(__('app.settings.placeholders.prefix'))
                            ->label(__('app.settings.fields.prefix')),
                        TextInput::make('invoice.last_number')
                            ->numeric()
                            ->label(__('app.settings.fields.last_number'))
                            ->maxLength(10),
                        Select::make('invoice.name_type')
                            ->native(false)
                            ->label(__('app.settings.fields.name_type'))
                            ->options([
                                'gym_name' => __('app.settings.options.name_type.gym_name'),
                                'gym_logo' => __('app.settings.options.name_type.gym_logo'),
                            ]),
                    ]),
                Fieldset::make(__('app.settings.sections.email'))
                    ->columns(['default' => 1, 'md' => 5])
                    ->schema([
                        Group::make()
                            ->schema([
                                TextInput::make('notifications.email.invoice_subject_template')
                                    ->label(__('app.settings.fields.email_invoice_subject'))
                                    ->placeholder(__('app.settings.placeholders.invoice_email_subject'))
                                    ->helperText(__('app.settings.hints.tokens_invoice')),
                                TextInput::make('notifications.email.receipt_subject_template')
                                    ->label(__('app.settings.fields.email_receipt_subject'))
                                    ->placeholder(__('app.settings.placeholders.receipt_email_subject'))
                                    ->helperText(__('app.settings.hints.tokens_receipt')),
                            ])->columnSpan(['default' => 1, 'md' => 3]),
                        Group::make()
                            ->schema([
                                Toggle::make('notifications.email.enabled')
                                    ->label(__('app.settings.fields.email_enabled'))
                                    ->default(false)
                                    ->inlineLabel(),
                                Toggle::make('notifications.email.auto_send_invoice_issued')
                                    ->label(__('app.settings.fields.auto_send_invoice_issued'))
                                    ->default(false)
                                    ->inlineLabel(),
                                Toggle::make('notifications.email.auto_send_payment_receipt')
                                    ->label(__('app.settings.fields.auto_send_payment_receipt'))
                                    ->default(false)
                                    ->inlineLabel(),
                            ])
                            ->columns(1)
                            ->columnSpan(['default' => 1, 'md' => 2]),
                    ]),
            ]);
    }

    /**
     * Member Tab Schema.
     */
    private function memberTab(): Tab
    {
        return
            Tab::make(__('app.settings.tabs.member'))->icon('heroicon-m-user-group')
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make('member.prefix')
                            ->placeholder(__('app.settings.placeholders.prefix'))
                            ->label(__('app.settings.fields.prefix')),
                        TextInput::make('member.last_number')
                            ->numeric()
                            ->label(__('app.settings.fields.last_number'))
                            ->maxLength(10),
                    ]),
            ]);
    }

    /**
     * Charges Tab Schema.
     */
    private function chargesTab(): Tab
    {
        return
            Tab::make(__('app.settings.tabs.charges'))->icon('heroicon-m-currency-rupee')
            ->schema([
                Grid::make(3)
                    ->schema([
                        TextInput::make('charges.admission_fee')
                            ->numeric()
                            ->label(__('app.settings.fields.admission_fee')),
                        TextInput::make('charges.taxes')
                            ->numeric()
                            ->label(__('app.settings.fields.taxes'))
                            ->suffix('%'),
                        TagsInput::make('charges.discounts')
                            ->label(__('app.settings.fields.discount_percent_available'))
                            ->hint(__('app.settings.hints.press_enter_to_add'))
                            ->placeholder(__('app.settings.hints.type_discount'))
                            ->separator(','),
                    ]),
            ]);
    }

    /**
     * Expenses Tab Schema.
     */
    private function expensesTab(): Tab
    {
        return
            Tab::make(__('app.settings.tabs.expenses'))->icon('heroicon-m-banknotes')
            ->schema([
                TagsInput::make('expenses.categories')
                    ->label(__('app.settings.fields.categories'))
                    ->hint(__('app.settings.hints.press_enter_to_add'))
                    ->placeholder(__('app.settings.hints.type_category'))
                    ->separator(','),
            ]);
    }

    /**
     * Subscriptions Tab Schema.
     */
    private function subscriptionsTab(): Tab
    {
        return
            Tab::make(__('app.settings.tabs.subscriptions'))->icon('heroicon-m-ticket')
            ->schema([
                TextInput::make('subscriptions.expiring_days')
                    ->label(__('app.settings.fields.expiring_days'))
                    ->numeric()
                    ->minValue(1)
                    ->default(7)
                    ->required(),
            ]);
    }

    /**
     * Configures a form instance by setting its schema and state path.
     *
     * @param  Schema  $schema  The form instance to configure.
     * @return Schema The configured form instance.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data');
    }

    /**
     * Persist the current settings.
     */
    public function save(): void
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

        foreach (['gym_logo'] as $logoKey) {
            $value = $general[$logoKey] ?? null;
            if (is_array($value)) {
                $general[$logoKey] = $value[0] ?? null;
            }
        }

        $settings['general'] = $general;

        try {
            app(SettingsRepository::class)->put($settings);
            $this->form->fill($settings);
        } catch (\Throwable $exception) {
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
