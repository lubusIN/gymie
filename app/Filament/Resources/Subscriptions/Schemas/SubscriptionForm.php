<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\RelationManagers\SubscriptionsRelationManager;
use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Plan;
use App\Support\Billing\InvoiceCalculator;
use App\Support\Billing\PaymentMethod;
use App\Support\Data;
use App\Support\Filament\DiscountSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

final class SubscriptionForm
{
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
                            ->getOptionLabelFromRecordUsing(fn (Plan $record): string => SubscriptionFormSupport::planOptionLabel($record))
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                $fee = SubscriptionFormSupport::planFee($get);
                                $taxRate = Helpers::getTaxRate() ?: 0;

                                foreach (self::invoiceItems($get) as $invoiceKey => $invoice) {
                                    $summary = InvoiceCalculator::summary(
                                        $fee,
                                        $taxRate,
                                        Data::float($invoice['discount_amount'] ?? 0),
                                        Data::float($invoice['paid_amount'] ?? 0),
                                    );
                                    $invoiceKey = (string) $invoiceKey;

                                    $set("invoices.{$invoiceKey}.subscription_fee", $summary['fee']);
                                    $set("invoices.{$invoiceKey}.tax", $summary['tax']);
                                    $set("invoices.{$invoiceKey}.total_amount", $summary['total']);
                                    $set("invoices.{$invoiceKey}.paid_amount", $summary['paid']);
                                    $set("invoices.{$invoiceKey}.due_amount", $summary['due']);
                                }

                                SubscriptionFormSupport::updateEndDate($get, $set);
                            })
                            ->required(),
                        DatePicker::make('start_date')
                            ->label(__('app.fields.start_date'))
                            ->live()
                            ->required()
                            ->default(now())
                            ->afterStateUpdated(fn (Get $get, Set $set) => SubscriptionFormSupport::updateEndDate($get, $set)),
                        DatePicker::make('end_date')
                            ->label(__('app.fields.end_date'))
                            ->live()
                            ->disabled()
                            ->dehydrated()
                            ->default(fn (Get $get): string => SubscriptionFormSupport::subscriptionEndDate($get))
                            ->afterStateHydrated(fn (Get $get, Set $set) => SubscriptionFormSupport::updateEndDate($get, $set)),
                    ]),
                Section::make(__('app.titles.invoice_details'))
                    ->hiddenOn('edit')
                    ->columnSpanFull()
                    ->schema([
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
                                                SubscriptionFormSupport::stringState($get, 'date'),
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
                                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                                $fee = SubscriptionFormSupport::floatState($get, 'subscription_fee');
                                                $discountPct = SubscriptionFormSupport::intState($get, 'discount') ?? 0;
                                                $discountAmount = Helpers::getDiscountAmount($discountPct, $fee);

                                                $set('discount_amount', round($discountAmount));
                                                SubscriptionFormSupport::recalculateInvoiceSummary($get, $set);
                                            }),
                                        TextInput::make('discount_amount')
                                            ->label(__('app.fields.discount_amount'))
                                            ->numeric()
                                            ->debounce(300)
                                            ->default(0)
                                            ->prefix(Helpers::getCurrencySymbol())
                                            ->maxValue(fn (Get $get): float => SubscriptionFormSupport::floatState($get, 'subscription_fee'))
                                            ->afterStateUpdated(function (Get $get, Set $set, $livewire, TextInput $component): void {
                                                $livewire->validateOnly($component->getStatePath());

                                                $fee = SubscriptionFormSupport::floatState($get, 'subscription_fee');
                                                $entered = SubscriptionFormSupport::floatState($get, 'discount_amount');
                                                $set('discount_amount', min(max($entered, 0), $fee));

                                                SubscriptionFormSupport::recalculateInvoiceSummary($get, $set);
                                            }),
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
                                            ->visible(fn (Get $get): bool => ! PaymentMethod::isOnline(SubscriptionFormSupport::stringState($get, 'payment_method')))
                                            ->afterStateUpdated(function (Get $get, Set $set, $livewire, TextInput $component): void {
                                                $livewire->validateOnly($component->getStatePath());
                                                SubscriptionFormSupport::recalculateInvoiceSummary($get, $set);
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

                                                SubscriptionFormSupport::recalculateInvoiceSummary($get, $set);
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
                    ]),
            ]);
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
}
