<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\RelationManagers\SubscriptionsRelationManager;
use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\AppConfig;
use App\Support\Billing\InvoiceCalculator;
use App\Support\Billing\PaymentMethod;
use App\Support\Data;
use App\Support\Filament\DiscountSelect;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
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
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class SubscriptionForm
{
    /**
     * Default payment method options for forms.
     *
     * @return array<string, string>
     */
    private static function paymentMethodOptions(): array
    {
        return PaymentMethod::options();
    }

    /**
     * Configure the subscription form schema.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Group::make()
                    ->columns(6)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('member_id')
                            ->columnSpan(2)
                            ->relationship('member', 'name')
                            ->placeholder(__('app.placeholders.select_member'))
                            ->getOptionLabelFromRecordUsing(fn (Member $record): string => "{$record->code} - {$record->name}")
                            ->hiddenOn([SubscriptionsRelationManager::class, CreateMember::class])
                            ->required(),
                        Select::make('plan_id')
                            ->columnSpan(fn ($livewire) => ($livewire instanceof SubscriptionsRelationManager ||
                                $livewire instanceof CreateMember)
                                ? 4
                                : 2)
                            ->relationship('plan', 'name')
                            ->placeholder(__('app.placeholders.select_plan'))
                            ->searchable(['code', 'name'])
                            ->live()
                            ->getOptionLabelFromRecordUsing(fn (Plan $record): string => self::formatPlanOptionLabel($record))
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $plan = self::planFromState($get);
                                $fee = (float) ($plan->amount ?? 0);
                                $taxRate = Helpers::getTaxRate() ?: 0;

                                $invoices = self::invoiceItems($get);

                                foreach ($invoices as $invoiceKey => $invoice) {
                                    $discount = Data::float($invoice['discount_amount'] ?? 0);
                                    $paid = Data::float($invoice['paid_amount'] ?? 0);
                                    $invoiceKey = (string) $invoiceKey;

                                    $summary = InvoiceCalculator::summary(
                                        $fee,
                                        $taxRate,
                                        $discount,
                                        $paid,
                                    );

                                    $set("invoices.{$invoiceKey}.subscription_fee", $summary['fee']);
                                    $set("invoices.{$invoiceKey}.tax", $summary['tax']);
                                    $set("invoices.{$invoiceKey}.total_amount", $summary['total']);
                                    $set("invoices.{$invoiceKey}.paid_amount", $summary['paid']);
                                    $set("invoices.{$invoiceKey}.due_amount", $summary['due']);
                                }

                                $set('end_date', Helpers::calculateSubscriptionEndDate(
                                    self::stringState($get, 'start_date'),
                                    self::intState($get, 'plan_id'),
                                ));
                            })
                            ->required(),
                        DatePicker::make('start_date')
                            ->label(__('app.fields.start_date'))
                            ->live()
                            ->required()
                            ->default(now())
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $set('end_date', Helpers::calculateSubscriptionEndDate(
                                    self::stringState($get, 'start_date'),
                                    self::intState($get, 'plan_id'),
                                ));
                            }),
                        DatePicker::make('end_date')
                            ->label(__('app.fields.end_date'))
                            ->live()
                            ->disabled()
                            ->dehydrated()
                            ->default(fn (Get $get): string => Helpers::calculateSubscriptionEndDate(
                                self::stringState($get, 'start_date'),
                                self::intState($get, 'plan_id'),
                            ))
                            ->afterStateHydrated(function (Get $get, Set $set) {
                                $set('end_date', Helpers::calculateSubscriptionEndDate(
                                    self::stringState($get, 'start_date'),
                                    self::intState($get, 'plan_id'),
                                ));
                            }),
                    ]),
                Section::make(__('app.titles.invoice_details'))
                    ->hiddenOn('edit')
                    ->columnSpanFull()
                    ->schema(
                        [
                            Repeater::make('invoices')
                                ->relationship('invoices')
                                ->itemLabel('')
                                ->hiddenLabel()
                                ->columnSpanFull()
                                ->minItems(1)
                                ->defaultItems(1)
                                ->maxItems(1)
                                ->addable(false)
                                ->deletable(false)
                                ->columns(4)
                                ->extraAttributes(['class' => 'rmv_rept-space'])
                                ->schema([
                                    Group::make()
                                        ->columns(2)
                                        ->columnSpan(3)
                                        ->schema([
                                            TextInput::make('number')
                                                ->label(__('app.fields.invoice_number'))
                                                ->required()
                                                ->readOnly()
                                                ->disabled()
                                                ->dehydrated()
                                                ->rule(Rule::unique('invoices', 'number'))
                                                ->default(fn (Get $get) => Helpers::generateLastNumber(
                                                    'invoice',
                                                    Invoice::class,
                                                    self::stringState($get, 'date')
                                                )),
                                            DatePicker::make('date')
                                                ->label(__('app.fields.date'))
                                                ->required()
                                                ->live()
                                                ->default(now()),
                                            DatePicker::make('due_date')
                                                ->label(__('app.fields.due_date'))
                                                ->required()
                                                ->live(),
                                            DiscountSelect::make('discount')
                                                ->afterStateUpdated(
                                                    function (Get $get, Set $set) {
                                                        $fee = self::floatState($get, 'subscription_fee');
                                                        $discountPct = self::intState($get, 'discount') ?? 0;
                                                        $discountAmount = Helpers::getDiscountAmount($discountPct, $fee);

                                                        $set('discount_amount', round($discountAmount));
                                                        self::recalculateInvoiceSummary($get, $set);
                                                    }
                                                ),
                                            TextInput::make('discount_amount')
                                                ->label(__('app.fields.discount_amount'))
                                                ->numeric()
                                                ->debounce(300)
                                                ->default(0)
                                                ->prefix(Helpers::getCurrencySymbol())
                                                ->maxValue(fn (Get $get): float => self::floatState($get, 'subscription_fee'))
                                                ->afterStateUpdated(
                                                    function (Get $get, Set $set, $livewire, TextInput $component) {
                                                        $livewire->validateOnly($component->getStatePath());

                                                        $fee = self::floatState($get, 'subscription_fee');
                                                        $entered = self::floatState($get, 'discount_amount');
                                                        $clamped = min(max($entered, 0), $fee);
                                                        $set('discount_amount', $clamped);

                                                        self::recalculateInvoiceSummary($get, $set);
                                                    }
                                                ),
                                            Textarea::make('discount_note')
                                                ->label(__('app.fields.discount_note'))
                                                ->placeholder(__('app.placeholders.discount_note_example')),
                                            TextInput::make('paid_amount')
                                                ->label(__('app.fields.paid_amount'))
                                                ->numeric()
                                                ->minValue(0)
                                                ->debounce(300)
                                                ->default(0)
                                                ->prefix(Helpers::getCurrencySymbol())
                                                ->visible(fn (Get $get): bool => ! PaymentMethod::isOnline(self::stringState($get, 'payment_method')))
                                                ->afterStateUpdated(function (Get $get, Set $set, $livewire, TextInput $component) {
                                                    $livewire->validateOnly($component->getStatePath());
                                                    self::recalculateInvoiceSummary($get, $set);
                                                }),
                                            Radio::make('payment_method')
                                                ->label(__('app.fields.payment_method'))
                                                ->options(self::paymentMethodOptions())
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
                                        ]),
                                    Fieldset::make(__('app.titles.summary'))
                                        ->columns(1)
                                        ->columnSpan(1)
                                        ->schema([
                                            TextInput::make('subscription_fee')
                                                ->label(__('app.fields.subscription_fee'))
                                                ->numeric()
                                                ->readOnly()
                                                ->disabled()
                                                ->dehydrated()
                                                ->default(0)
                                                ->prefix(Helpers::getCurrencySymbol())
                                                ->required(),
                                            TextInput::make('tax')
                                                ->label(fn (): string => __('app.fields.tax_with_rate', ['rate' => Helpers::getTaxRate()]))
                                                ->numeric()
                                                ->disabled()
                                                ->dehydrated()
                                                ->default(0)
                                                ->prefix(Helpers::getCurrencySymbol())
                                                ->readOnly(),
                                            TextInput::make('total_amount')
                                                ->label(__('app.fields.total_amount'))
                                                ->numeric()
                                                ->readOnly()
                                                ->disabled()
                                                ->dehydrated()
                                                ->default(0)
                                                ->prefix(Helpers::getCurrencySymbol())
                                                ->required(),
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
                        ]
                    ),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    public static function renewSchema(Subscription $record): array
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
                                $plan->id => self::formatPlanOptionLabel($plan),
                            ])
                            ->all())
                        ->searchable()
                        ->default($record->plan_id)
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            $set('end_date', Helpers::calculateSubscriptionEndDate(
                                self::stringState($get, 'start_date'),
                                self::intState($get, 'plan_id'),
                            ));

                            $plan = self::planFromState($get);
                            $fee = round(Data::float($plan?->amount));
                            $discountPct = self::intState($get, 'discount') ?? 0;
                            $discountAmount = round(Helpers::getDiscountAmount($discountPct, $fee));
                            $set('discount_amount', $discountAmount);

                            self::recalculateRenewInvoiceSummary($get, $set);
                        })
                        ->required()
                        ->columnSpan(3),
                    DatePicker::make('start_date')
                        ->label(__('app.fields.start_date'))
                        ->native(false)
                        ->suffixIcon('heroicon-m-calendar-days')
                        ->default($defaultStartDate)
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set): void {
                            $set('end_date', Helpers::calculateSubscriptionEndDate(
                                self::stringState($get, 'start_date'),
                                self::intState($get, 'plan_id'),
                            ));

                            self::recalculateRenewInvoiceSummary($get, $set);
                        })
                        ->required(),
                    DatePicker::make('end_date')
                        ->label(__('app.fields.end_date'))
                        ->native(false)
                        ->suffixIcon('heroicon-m-calendar-days')
                        ->disabled()
                        ->dehydrated()
                        ->default(fn (Get $get): string => Helpers::calculateSubscriptionEndDate(
                            self::stringState($get, 'start_date'),
                            self::intState($get, 'plan_id'),
                        ))
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
                                    self::stringState($get, 'invoice_date'),
                                )),
                            DatePicker::make('invoice_date')
                                ->label(__('app.fields.invoice_date'))
                                ->native(false)
                                ->suffixIcon('heroicon-m-calendar-days')
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
                                ->suffixIcon('heroicon-m-calendar-days')
                                ->default($today)
                                ->required(),
                            DiscountSelect::make('discount')
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    $plan = self::planFromState($get);
                                    $fee = round(Data::float($plan?->amount));
                                    $discountPct = self::intState($get, 'discount') ?? 0;
                                    $discountAmount = round(Helpers::getDiscountAmount($discountPct, $fee));
                                    $set('discount_amount', $discountAmount);

                                    self::recalculateRenewInvoiceSummary($get, $set);
                                }),
                            TextInput::make('discount_amount')
                                ->label(__('app.fields.discount_amount'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(function (Get $get): float {
                                    $plan = self::planFromState($get);

                                    return round(Data::float($plan?->amount));
                                })
                                ->debounce(300)
                                ->default(0)
                                ->prefix(Helpers::getCurrencySymbol())
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    self::recalculateRenewInvoiceSummary($get, $set);
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
                                ->visible(fn (Get $get): bool => ! PaymentMethod::isOnline(self::stringState($get, 'payment_method')))
                                ->afterStateUpdated(function (Get $get, Set $set): void {
                                    self::recalculateRenewInvoiceSummary($get, $set);
                                }),
                            Radio::make('payment_method')
                                ->label(__('app.fields.payment_method'))
                                ->options(self::paymentMethodOptions())
                                ->default('cash')
                                ->inline()
                                ->inlineLabel(false)
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                    if (PaymentMethod::isOnline($state)) {
                                        $set('paid_amount', 0);
                                    }

                                    self::recalculateRenewInvoiceSummary($get, $set);
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
                                ->default(function (Get $get): float {
                                    $plan = self::planFromState($get);

                                    return round(Data::float($plan?->amount));
                                })
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
     * Handle the subscription renewal process, including creating a new subscription and associated invoice.
     *
     * @param  Subscription  $record  The subscription being renewed
     * @param  array<string, mixed>  $data  The form data for the new subscription and invoice
     */
    public static function handleRenew(Subscription $record, array $data): void
    {
        Subscription::query()->getConnection()->transaction(function () use ($record, $data): void {
            $timezone = AppConfig::timezone();
            $today = Carbon::today($timezone);

            $plan = Plan::findOrFail(Data::int($data['plan_id'] ?? null));
            $startDate = Carbon::parse(Data::string($data['start_date'] ?? null))->toDateString();
            $endDate = Data::string($data['end_date'] ?? null)
                ?: Helpers::calculateSubscriptionEndDate($startDate, Data::int($plan->id));

            $status = Carbon::parse($startDate)->gt($today)
                ? 'upcoming'
                : 'ongoing';

            $newSubscription = Subscription::create([
                'renewed_from_subscription_id' => $record->id,
                'member_id' => $record->member_id,
                'plan_id' => $plan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ]);

            if ($record->end_date && $record->end_date->lt($today)) {
                $record->update([
                    'status' => 'renewed',
                ]);
            }

            $fee = round(Data::float($plan->amount));
            $discountPct = max(Data::int($data['discount'] ?? 0), 0);
            $discountAmount = Data::float($data['discount_amount'] ?? 0);
            $discountAmount = min(max($discountAmount, 0), $fee);
            if ($discountPct > 0 && $discountAmount <= 0) {
                $discountAmount = Helpers::getDiscountAmount($discountPct, $fee);
            }

            $paymentMethod = Data::nullableString($data['payment_method'] ?? null);
            $paidAmount = max(Data::float($data['paid_amount'] ?? 0), 0);
            if (PaymentMethod::isOnline($paymentMethod)) {
                $paidAmount = 0;
            }

            $invoiceDate = Carbon::parse(Data::string($data['invoice_date'] ?? null))->toDateString();
            $invoiceDueDate = Carbon::parse(Data::string($data['invoice_due_date'] ?? $invoiceDate))->toDateString();

            $invoice = Invoice::create([
                'number' => $data['invoice_number'] ?? null,
                'subscription_id' => $newSubscription->id,
                'date' => $invoiceDate,
                'due_date' => $invoiceDueDate,
                'payment_method' => $paymentMethod,
                'discount' => $discountPct ?: null,
                'discount_amount' => $discountAmount ?: null,
                'discount_note' => $data['discount_note'] ?? null,
                'paid_amount' => $paidAmount,
                'subscription_fee' => $fee,
                'status' => 'issued',
            ]);

            Notification::make()
                ->title(__('app.notifications.subscription_renewed_title'))
                ->body(__('app.notifications.subscription_renewed_body', ['invoice_number' => (string) $invoice->number]))
                ->success()
                ->send();
        });
    }

    /**
     * Recalculate invoice summary fields (subscription_fee, tax, total_amount, due_amount) based on the selected plan and discount.
     */
    private static function recalculateRenewInvoiceSummary(Get $get, Set $set): void
    {
        $plan = self::planFromState($get);
        $fee = (float) ($plan->amount ?? 0);
        $taxRate = Helpers::getTaxRate() ?: 0;

        self::recalculateInvoiceSummary($get, $set, $fee, $taxRate);
    }

    /**
     * Recalculate invoice summary fields (subscription_fee, tax, total_amount, due_amount).
     */
    private static function recalculateInvoiceSummary(Get $get, Set $set, ?float $fee = null, ?float $taxRate = null): void
    {
        $fee = $fee ?? self::floatState($get, 'subscription_fee');
        $taxRate = $taxRate ?? (float) (Helpers::getTaxRate() ?: 0);

        $discountAmount = self::floatState($get, 'discount_amount');
        $paid = self::floatState($get, 'paid_amount');

        $paymentMethod = self::stringState($get, 'payment_method');
        if (PaymentMethod::isOnline($paymentMethod)) {
            $paid = 0;
        }

        $summary = InvoiceCalculator::summary(
            $fee,
            $taxRate,
            $discountAmount,
            $paid,
        );

        $set('subscription_fee', $summary['fee']);
        $set('tax', $summary['tax']);
        $set('discount_amount', $summary['discount_amount']);
        $set('total_amount', $summary['total']);
        $set('paid_amount', $summary['paid']);
        $set('due_amount', $summary['due']);
    }

    /**
     * Format the plan option label for the select input.
     */
    private static function formatPlanOptionLabel(Plan $plan): string
    {
        $days = Data::int($plan->days);

        return sprintf(
            '%s – %s (%s%s | %d %s)',
            $plan->code,
            $plan->name,
            Helpers::getCurrencySymbol(),
            round((float) $plan->amount),
            $days,
            $days === 1 ? __('app.units.day') : __('app.units.days'),
        );
    }

    /**
     * @return array<array-key, array<string, mixed>>
     */
    private static function invoiceItems(Get $get): array
    {
        $items = $get('invoices');

        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $key => $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized[$key] = Data::map($item);
        }

        return $normalized;
    }

    private static function stringState(Get $get, string $path): ?string
    {
        return Data::nullableString($get($path));
    }

    private static function intState(Get $get, string $path): ?int
    {
        $value = $get($path);

        return is_numeric($value) ? (int) $value : null;
    }

    private static function floatState(Get $get, string $path): float
    {
        return Data::float($get($path));
    }

    private static function planFromState(Get $get): ?Plan
    {
        $planId = self::intState($get, 'plan_id');

        return $planId !== null ? Plan::find($planId) : null;
    }
}
