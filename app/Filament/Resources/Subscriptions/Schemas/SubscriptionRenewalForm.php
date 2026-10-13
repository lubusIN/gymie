<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionRenewalService;
use App\Support\AppConfig;
use App\Support\Billing\PaymentMethod;
use App\Support\Data;
use App\Support\Filament\DiscountSelect;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\Rule;

final class SubscriptionRenewalForm
{
    /**
     * @return array<int, Component>
     */
    public static function schema(Subscription $record): array
    {
        $today = Carbon::today(AppConfig::timezone())->toDateString();
        $defaultStartDate = max(
            $today,
            $record->end_date?->copy()->addDay()->toDateString() ?? $today,
        );

        return [
            Group::make()
                ->columns(5)
                ->schema([
                    Select::make('plan_id')
                        ->label(__('app.fields.plan'))
                        ->options(fn (): array => Plan::query()
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Plan $plan): array => [
                                $plan->id => SubscriptionFormSupport::planOptionLabel($plan),
                            ])
                            ->all())
                        ->searchable()
                        ->default($record->plan_id)
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            SubscriptionFormSupport::updateEndDate($get, $set);

                            $fee = SubscriptionFormSupport::planFee($get);
                            $discountPct = SubscriptionFormSupport::intState($get, 'discount') ?? 0;
                            $discountAmount = round(Helpers::getDiscountAmount($discountPct, $fee));
                            $set('discount_amount', $discountAmount);

                            self::recalculateInvoiceSummary($get, $set);
                        })
                        ->required()
                        ->columnSpan(3),
                    DatePicker::make('start_date')
                        ->label(__('app.fields.start_date'))
                        ->native(false)
                        ->suffixIcon('heroicon-o-calendar-days')
                        ->default($defaultStartDate)
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            SubscriptionFormSupport::updateEndDate($get, $set);

                            self::recalculateInvoiceSummary($get, $set);
                        })
                        ->required(),
                    DatePicker::make('end_date')
                        ->label(__('app.fields.end_date'))
                        ->native(false)
                        ->suffixIcon('heroicon-o-calendar-days')
                        ->disabled()
                        ->dehydrated()
                        ->default(fn (Get $get): string => SubscriptionFormSupport::subscriptionEndDate($get))
                        ->required(),
                ]),
            Section::make(__('app.resources.invoices.singular'))
                ->columns(7)
                ->schema([
                    Group::make()
                        ->columns(2)
                        ->schema([
                            TextInput::make('invoice_number')
                                ->label(__('app.fields.invoice_number'))
                                ->required()
                                ->readOnly()
                                ->disabled()
                                ->dehydrated()
                                ->rule(Rule::unique('invoices', 'number'))
                                ->default(fn (Get $get) => Helpers::generateLastNumber(
                                    'invoice',
                                    Invoice::class,
                                    SubscriptionFormSupport::stringState($get, 'invoice_date'),
                                )),
                            DatePicker::make('invoice_date')
                                ->label(__('app.fields.invoice_date'))
                                ->native(false)
                                ->suffixIcon('heroicon-o-calendar-days')
                                ->default($today)
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                    $set('invoice_number', Helpers::generateLastNumber(
                                        'invoice',
                                        Invoice::class,
                                        $state,
                                    ));

                                    if (blank($get('invoice_due_date'))) {
                                        $set('invoice_due_date', $state);
                                    }
                                })
                                ->required(),
                            DatePicker::make('invoice_due_date')
                                ->label(__('app.fields.due_date'))
                                ->native(false)
                                ->suffixIcon('heroicon-o-calendar-days')
                                ->default($today)
                                ->required(),
                            DiscountSelect::make('discount')
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    $fee = SubscriptionFormSupport::planFee($get);
                                    $discountPct = SubscriptionFormSupport::intState($get, 'discount') ?? 0;
                                    $discountAmount = round(Helpers::getDiscountAmount($discountPct, $fee));
                                    $set('discount_amount', $discountAmount);

                                    self::recalculateInvoiceSummary($get, $set);
                                }),
                            TextInput::make('discount_amount')
                                ->label(__('app.fields.discount_amount'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(fn (Get $get): float => SubscriptionFormSupport::planFee($get))
                                ->debounce(300)
                                ->default(0)
                                ->prefix(Helpers::getCurrencySymbol())
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    self::recalculateInvoiceSummary($get, $set);
                                }),
                            Textarea::make('discount_note')
                                ->label(__('app.fields.discount_note'))
                                ->placeholder(__('app.placeholders.discount_note_renewal_example')),
                            TextInput::make('paid_amount')
                                ->label(__('app.fields.paid_amount'))
                                ->numeric()
                                ->minValue(0)
                                ->debounce(300)
                                ->default(0)
                                ->prefix(Helpers::getCurrencySymbol())
                                ->visible(fn (Get $get): bool => ! PaymentMethod::isOnline(SubscriptionFormSupport::stringState($get, 'payment_method')))
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    self::recalculateInvoiceSummary($get, $set);
                                }),
                            Radio::make('payment_method')
                                ->label(__('app.fields.payment_method'))
                                ->options(PaymentMethod::options())
                                ->default('cash')
                                ->inline()
                                ->inlineLabel(false)
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                    if (PaymentMethod::isOnline($state)) {
                                        $set('paid_amount', 0);
                                    }

                                    self::recalculateInvoiceSummary($get, $set);
                                })
                                ->required(),
                        ])->columnSpan(5),
                    Fieldset::make(__('app.titles.summary'))
                        ->columns(1)
                        ->columnSpan(2)
                        ->schema([
                            TextInput::make('subscription_fee')
                                ->label(__('app.fields.subscription_fee'))
                                ->numeric()
                                ->readOnly()
                                ->disabled()
                                ->dehydrated()
                                ->default(fn (Get $get): float => SubscriptionFormSupport::planFee($get))
                                ->prefix(Helpers::getCurrencySymbol()),
                            TextInput::make('tax')
                                ->label(fn (): string => __('app.fields.tax_with_rate', ['rate' => Helpers::getTaxRate()]))
                                ->numeric()
                                ->readOnly()
                                ->disabled()
                                ->dehydrated()
                                ->default(0)
                                ->prefix(Helpers::getCurrencySymbol()),
                            TextInput::make('total_amount')
                                ->label(__('app.fields.total_amount'))
                                ->numeric()
                                ->readOnly()
                                ->disabled()
                                ->dehydrated()
                                ->default(0)
                                ->prefix(Helpers::getCurrencySymbol()),
                            TextInput::make('due_amount')
                                ->label(__('app.fields.due_amount'))
                                ->numeric()
                                ->readOnly()
                                ->disabled()
                                ->dehydrated()
                                ->default(0)
                                ->prefix(Helpers::getCurrencySymbol()),
                        ]),
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handle(
        SubscriptionRenewalService $subscriptionRenewalService,
        Subscription $record,
        array $data,
    ): void {
        $result = $subscriptionRenewalService->renew($record, [
            'plan_id' => Data::int($data['plan_id'] ?? null),
            'start_date' => Data::string($data['start_date'] ?? null),
            'end_date' => Data::nullableString($data['end_date'] ?? null),
            'invoice' => [
                'number' => Data::nullableString($data['invoice_number'] ?? null),
                'date' => Data::nullableString($data['invoice_date'] ?? null),
                'due_date' => Data::nullableString($data['invoice_due_date'] ?? null),
                'payment_method' => Data::nullableString($data['payment_method'] ?? null),
                'discount' => Data::int($data['discount'] ?? 0),
                'discount_amount' => Data::float($data['discount_amount'] ?? 0),
                'discount_note' => Data::nullableString($data['discount_note'] ?? null),
                'paid_amount' => Data::float($data['paid_amount'] ?? 0),
            ],
        ]);

        Notification::make()
            ->title(__('app.notifications.subscription_renewed_title'))
            ->body(__('app.notifications.subscription_renewed_body', [
                'invoice_number' => (string) $result['invoice']->number,
            ]))
            ->success()
            ->send();
    }

    private static function recalculateInvoiceSummary(Get $get, Set $set): void
    {
        SubscriptionFormSupport::recalculateInvoiceSummary(
            $get,
            $set,
            SubscriptionFormSupport::planFee($get),
            (float) (Helpers::getTaxRate() ?: 0),
        );
    }
}
