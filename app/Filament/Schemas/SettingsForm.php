<?php

namespace App\Filament\Schemas;

use App\Filament\Resources\CommunicationTemplates\CommunicationTemplateResource;
use App\Helpers\Helpers;
use App\Services\Communication\EmailDeliveryContext;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

final class SettingsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make(__('app.settings.title'))
                    ->tabs([
                        self::generalTab(),
                        self::invoiceTab(),
                        self::memberTab(),
                        self::chargesTab(),
                        self::expensesTab(),
                        self::subscriptionsTab(),
                    ]),
            ]);
    }

    private static function generalTab(): Tab
    {
        return Tab::make(__('app.settings.tabs.gym_info'))
            ->icon('heroicon-o-briefcase')
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
                                    ->options(Helpers::getCurrencies()),
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
                                    ->label(__('app.settings.fields.financial_year_start'))
                                    ->helperText('Rounded to the first day of that month.')
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        if (blank($state)) {
                                            $set('general.financial_year_end', null);

                                            return;
                                        }

                                        $start = Carbon::parse($state)->startOfMonth();

                                        $set('general.financial_year_start', $start->toDateString());
                                        $set('general.financial_year_end', $start->copy()->addYear()->subDay()->toDateString());
                                    }),
                                DatePicker::make('general.financial_year_end')
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
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('general.state', null);
                                        $set('general.city', null);
                                    }),
                                Select::make('general.state')
                                    ->label(__('app.settings.fields.state'))
                                    ->options(fn (Get $get): array => Helpers::getStates($get('general.country')))
                                    ->live(),
                                Select::make('general.city')
                                    ->label(__('app.settings.fields.city'))
                                    ->options(fn (Get $get): array => Helpers::getCities($get('general.state'))),
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
                                    ->helperText(__('app.settings.hints.gym_email_reply_to'))
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

    private static function invoiceTab(): Tab
    {
        return Tab::make(__('app.settings.tabs.invoice'))
            ->icon('heroicon-o-document-text')
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
                            ->label(__('app.settings.fields.name_type'))
                            ->options([
                                'gym_name' => __('app.settings.options.name_type.gym_name'),
                                'gym_logo' => __('app.settings.options.name_type.gym_logo'),
                            ]),
                    ]),
                Fieldset::make(__('app.settings.sections.email'))
                    ->columns(1)
                    ->schema([
                        Toggle::make('notifications.email.enabled')
                            ->label(__('app.settings.fields.email_enabled'))
                            ->helperText(__('app.settings.hints.email_enabled'))
                            ->default(false)
                            ->live(),
                        Section::make(__('app.settings.sections.email_delivery'))
                            ->key('email_delivery_settings')
                            ->columns(3)
                            ->visible(fn (Get $get): bool => (bool) $get('notifications.email.enabled'))
                            ->schema([
                                TextEntry::make('email_delivery_status')
                                    ->label(__('app.settings.fields.email_delivery_status'))
                                    ->state(fn (): string => app(EmailDeliveryContext::class)->isDeliveryConfigured()
                                        ? __('app.settings.options.email_delivery.configured')
                                        : __('app.settings.options.email_delivery.not_configured'))
                                    ->badge()
                                    ->color(fn (): string => app(EmailDeliveryContext::class)->isDeliveryConfigured()
                                        ? 'success'
                                        : 'danger'),
                                TextEntry::make('email_sender_address')
                                    ->label(__('app.settings.fields.email_sender_address'))
                                    ->state(fn (): string => filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
                                        ? (string) config('mail.from.address')
                                        : __('app.placeholders.na')),
                                TextEntry::make('email_reply_to_address')
                                    ->label(__('app.settings.fields.email_reply_to_address'))
                                    ->state(fn (Get $get): string => filled($get('general.gym_email'))
                                        ? (string) $get('general.gym_email')
                                        : __('app.placeholders.na')),
                                Actions::make([
                                    Action::make('manage_communication_templates')
                                        ->label(__('app.settings.actions.manage_communication_templates'))
                                        ->icon('heroicon-o-envelope')
                                        ->color('gray')
                                        ->url(CommunicationTemplateResource::getUrl('index'))
                                        ->visible(fn (): bool => CommunicationTemplateResource::canViewAny()),
                                ])->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    private static function memberTab(): Tab
    {
        return Tab::make(__('app.settings.tabs.member'))
            ->icon('heroicon-o-user-group')
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

    private static function chargesTab(): Tab
    {
        return Tab::make(__('app.settings.tabs.charges'))
            ->icon('heroicon-o-currency-rupee')
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

    private static function expensesTab(): Tab
    {
        return Tab::make(__('app.settings.tabs.expenses'))
            ->icon('heroicon-o-banknotes')
            ->schema([
                TagsInput::make('expenses.categories')
                    ->label(__('app.settings.fields.categories'))
                    ->hint(__('app.settings.hints.press_enter_to_add'))
                    ->placeholder(__('app.settings.hints.type_category'))
                    ->separator(','),
            ]);
    }

    private static function subscriptionsTab(): Tab
    {
        return Tab::make(__('app.settings.tabs.subscriptions'))
            ->icon('heroicon-o-ticket')
            ->schema([
                TextInput::make('subscriptions.expiring_days')
                    ->label(__('app.settings.fields.expiring_days'))
                    ->numeric()
                    ->minValue(1)
                    ->default(7)
                    ->required(),
            ]);
    }
}
