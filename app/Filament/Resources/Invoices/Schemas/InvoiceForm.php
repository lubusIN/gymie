<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Filament\Resources\Subscriptions\RelationManagers\InvoicesRelationManager;
use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Support\Billing\InvoiceCalculator;
use App\Support\Billing\PaymentMethod;
use App\Support\Data;
use App\Support\Filament\DiscountSelect;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class InvoiceForm
{
    /**
     * Configure the invoice form schema.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        Group::make()
                            ->columns(3)
                            ->columnSpan(3)
                            ->schema([
                                TextInput::make('number')
                                    ->label(__('app.fields.invoice_number'))
                                    ->required()
                                    ->readOnly()
                                    ->disabled()
                                    ->dehydrated()
                                    ->default(fn (Get $get) => Helpers::generateLastNumber(
                                        'invoice',
                                        Invoice::class,
                                        self::stringState($get, 'date')
                                    )),
                                Select::make('subscription_id')
                                    ->label(__('app.fields.subscription'))
                                    ->reactive()
                                    ->relationship(
                                        name: 'subscription',
                                        titleAttribute: 'id',
                                        modifyQueryUsing: fn (Builder $query) => $query
                                            ->with(['member', 'plan'])
                                            ->orderByDesc('start_date'),
                                    )
                                    ->hiddenOn(InvoicesRelationManager::class)
                                    ->getOptionLabelFromRecordUsing(fn (Subscription $record): string => self::formatSubscriptionOptionLabel($record))
                                    ->searchable()
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        $subscription = self::subscriptionFromState($get);

                                        if (! $subscription) {
                                            self::resetInvoiceSummary($set);

                                            return;
                                        }

                                        self::recalculateInvoiceSummary(
                                            $get,
                                            $set,
                                            (float) ($subscription->plan->amount ?? 0),
                                        );
                                    })
                                    ->required(),
                                DatePicker::make('date')
                                    ->label(__('app.fields.date'))
                                    ->required()
                                    ->reactive()
                                    ->default(now()),
                                DatePicker::make('due_date')
                                    ->label(__('app.fields.due_date'))
                                    ->required()
                                    ->reactive(),
                                DiscountSelect::make('discount')
                                    ->native(false)
                                    ->reactive()
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        $discountAmount = Helpers::getDiscountAmount(
                                            self::intState($get, 'discount'),
                                            self::floatState($get, 'subscription_fee'),
                                        );

                                        $set('discount_amount', $discountAmount);
                                        self::recalculateInvoiceSummary($get, $set);
                                    }),
                                TextInput::make('discount_amount')
                                    ->label(__('app.fields.discount_amount'))
                                    ->numeric()
                                    ->debounce(300)
                                    ->default(0)
                                    ->prefix(Helpers::getCurrencySymbol())
                                    ->maxValue(fn (Get $get): float => self::floatState($get, 'subscription_fee'))
                                    ->afterStateUpdated(function (Get $get, Set $set, $livewire, TextInput $component): void {
                                        $livewire->validateOnly($component->getStatePath());

                                        $fee = self::floatState($get, 'subscription_fee');
                                        $discountAmount = min(
                                            max(self::floatState($get, 'discount_amount'), 0),
                                            $fee,
                                        );

                                        $set('discount_amount', $discountAmount);
                                        self::recalculateInvoiceSummary($get, $set);
                                    }),
                                Textarea::make('discount_note')
                                    ->label(__('app.fields.discount_note'))
                                    ->placeholder(__('app.placeholders.discount_note_example')),
                                Radio::make('payment_method')
                                    ->label(__('app.fields.payment_method'))
                                    ->options(PaymentMethod::options())
                                    ->default('cash')
                                    ->inline()
                                    ->inlineLabel(false)
                                    ->required(),
                            ]),
                        Fieldset::make(__('app.titles.summary'))
                            ->columnSpan(1)
                            ->columns(1)
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
                            ]),
                    ]),
            ]);
    }

    /**
     * Format the subscription option label for display in the select input.
     *
     * @param  Subscription  $subscription  The subscription record to format.
     * @return string The formatted label for the subscription option.
     */
    private static function formatSubscriptionOptionLabel(Subscription $subscription): string
    {
        $memberCode = $subscription->member->code ?? '—';
        $memberName = $subscription->member->name ?? '—';
        $planCode = $subscription->plan->code ?? '—';
        $planName = $subscription->plan->name ?? '—';
        $start = $subscription->start_date?->format('d-m-Y') ?? '—';
        $end = $subscription->end_date?->format('d-m-Y') ?? '—';
        $status = $subscription->status?->getLabel() ?? '—';

        return "#{$subscription->id} — {$memberCode} {$memberName} • {$planCode} {$planName} • {$start} → {$end} • {$status}";
    }

    /**
     * Recalculate all derived invoice amount fields.
     */
    private static function recalculateInvoiceSummary(Get $get, Set $set, ?float $fee = null): void
    {
        $summary = InvoiceCalculator::summary(
            $fee ?? self::floatState($get, 'subscription_fee'),
            (float) (Helpers::getTaxRate() ?: 0),
            self::floatState($get, 'discount_amount'),
            self::floatState($get, 'paid_amount'),
        );

        $set('subscription_fee', $summary['fee']);
        $set('tax', $summary['tax']);
        $set('discount_amount', $summary['discount_amount']);
        $set('total_amount', $summary['total']);
        $set('paid_amount', $summary['paid']);
        $set('due_amount', $summary['due']);
    }

    /**
     * Clear derived amounts when no subscription is selected.
     */
    private static function resetInvoiceSummary(Set $set): void
    {
        $set('subscription_fee', 0);
        $set('tax', 0);
        $set('total_amount', 0);
        $set('discount_amount', 0);
        $set('due_amount', 0);
    }

    private static function subscriptionFromState(Get $get): ?Subscription
    {
        $subscriptionId = self::intState($get, 'subscription_id');

        return $subscriptionId > 0
            ? Subscription::with('plan')->find($subscriptionId)
            : null;
    }

    private static function stringState(Get $get, string $path): ?string
    {
        return Data::nullableString($get($path));
    }

    private static function intState(Get $get, string $path): int
    {
        return Data::int($get($path));
    }

    private static function floatState(Get $get, string $path): float
    {
        return Data::float($get($path));
    }
}
