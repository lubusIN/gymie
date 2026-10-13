<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\RelationManagers\InvoiceTransactionsRelationManager;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Support\Billing\PaymentMethod;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;

class InvoiceInfolist
{
    /**
     * Configure the invoice view workspace.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make(__('app.resources.invoices.singular'))
                    ->key('invoice_sections')
                    ->contained(false)
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('app.ui.details'))
                            ->key('details_tab')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'xl' => 3,
                                ])->schema([
                                    self::detailsSection(),
                                    self::summarySection(),
                                ]),
                            ]),
                        Tab::make(__('app.titles.payment_history'))
                            ->key('payment_history_tab')
                            ->icon('heroicon-o-banknotes')
                            ->visible(fn (Invoice $record): bool => InvoiceTransactionsRelationManager::canViewForRecord(
                                $record,
                                ViewInvoice::class,
                            ))
                            ->schema([
                                LivewireComponent::make(
                                    InvoiceTransactionsRelationManager::class,
                                    fn (Invoice $record): array => [
                                        'ownerRecord' => $record,
                                        'pageClass' => ViewInvoice::class,
                                    ],
                                )->key('invoice_transactions_relation_manager'),
                            ]),
                    ]),
            ]);
    }

    private static function detailsSection(): Section
    {
        return Section::make(__('app.titles.invoice_details'))
            ->key('invoice_details')
            ->columns(2)
            ->columnSpan([
                'default' => 1,
                'xl' => 2,
            ])
            ->schema([
                TextEntry::make('number')
                    ->label(__('app.fields.invoice_number'))
                    ->weight(FontWeight::Bold)
                    ->copyable(),
                TextEntry::make('status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('subscription.member.name')
                    ->label(__('app.fields.member'))
                    ->helperText(fn (Invoice $record): ?string => $record->subscription?->member?->code)
                    ->url(fn (Invoice $record): ?string => $record->subscription?->member
                        ? MemberResource::getUrl('view', ['record' => $record->subscription->member])
                        : null),
                TextEntry::make('subscription.plan.name')
                    ->label(__('app.fields.plan'))
                    ->helperText(fn (Invoice $record): ?string => $record->subscription?->plan?->code)
                    ->url(fn (Invoice $record): ?string => $record->subscription
                        ? SubscriptionResource::getUrl('view', ['record' => $record->subscription])
                        : null),
                TextEntry::make('date')
                    ->label(__('app.fields.invoice_date'))
                    ->date('d M Y'),
                TextEntry::make('due_date')
                    ->label(__('app.fields.due_date'))
                    ->date('d M Y')
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('subscription.start_date')
                    ->label(__('app.fields.start_date'))
                    ->date('d M Y')
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('subscription.end_date')
                    ->label(__('app.fields.end_date'))
                    ->date('d M Y')
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('payment_method')
                    ->label(__('app.fields.payment_method'))
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? PaymentMethod::channelLabel($state)
                        : __('app.placeholders.na')),
                TextEntry::make('discount_note')
                    ->label(__('app.fields.discount_note'))
                    ->placeholder(__('app.placeholders.na')),
            ]);
    }

    private static function summarySection(): Section
    {
        return Section::make(__('app.titles.summary'))
            ->key('invoice_summary')
            ->columns(2)
            ->columnSpan(1)
            ->schema([
                TextEntry::make('subscription_fee')
                    ->label(__('app.fields.fee'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state)),
                TextEntry::make('tax')
                    ->label(fn (): string => __('app.fields.tax_with_rate', ['rate' => Helpers::getTaxRate()]))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->visible(fn (Invoice $record): bool => (float) $record->tax > 0),
                TextEntry::make('discount_amount')
                    ->label(fn (Invoice $record): string => $record->discount
                        ? __('app.fields.discount_with_rate', ['rate' => $record->discount])
                        : __('app.fields.discount'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->visible(fn (Invoice $record): bool => (float) $record->discount_amount > 0),
                TextEntry::make('total_amount')
                    ->label(__('app.fields.total'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->columnSpanFull(),
                TextEntry::make('paid_amount')
                    ->label(__('app.fields.paid'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->color('success'),
                TextEntry::make('due_amount')
                    ->label(__('app.fields.due'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),
            ]);
    }
}
