<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Filament\Infolists\Components\ActivityTimelineEntry;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Filament\Resources\Subscriptions\RelationManagers\InvoicesRelationManager;
use App\Helpers\Helpers;
use App\Models\Subscription;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class SubscriptionInfolist
{
    /**
     * Configure the subscription "view" infolist schema.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make(__('app.resources.subscriptions.singular'))
                    ->key('subscription_sections')
                    ->contained(false)
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('app.ui.details'))
                            ->key('details_tab')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'xl' => 2,
                                ])->schema([
                                    self::membershipSection(),
                                    self::billingSection(),
                                ]),
                            ]),
                        Tab::make(__('app.activity.title'))
                            ->key('activity_tab')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                Section::make(__('app.activity.title'))
                                    ->columns(1)
                                    ->schema([
                                        ActivityTimelineEntry::make('activity_timeline')
                                            ->hiddenLabel(),
                                    ]),
                            ]),
                        Tab::make(__('app.resources.invoices.singular'))
                            ->key('invoice_tab')
                            ->icon('heroicon-o-document-text')
                            ->visible(fn (Subscription $record): bool => InvoicesRelationManager::canViewForRecord(
                                $record,
                                ViewSubscription::class,
                            ))
                            ->schema([
                                LivewireComponent::make(
                                    InvoicesRelationManager::class,
                                    fn (Subscription $record): array => [
                                        'ownerRecord' => $record,
                                        'pageClass' => ViewSubscription::class,
                                    ],
                                )->key('invoice_relation_manager'),
                            ]),
                    ]),
            ]);
    }

    private static function membershipSection(): Section
    {
        return Section::make(__('app.resources.subscriptions.singular'))
            ->columns(2)
            ->schema([
                TextEntry::make('member.name')
                    ->label(__('app.fields.member'))
                    ->helperText(fn (Subscription $record): string => $record->member->code)
                    ->url(fn (Subscription $record): string => MemberResource::getUrl('view', [
                        'record' => $record->member,
                    ]))
                    ->columnSpanFull(),
                TextEntry::make('plan.name')
                    ->label(__('app.fields.plan'))
                    ->helperText(fn (Subscription $record): string => $record->plan->code)
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->label(__('app.fields.status'))
                    ->badge(),
                TextEntry::make('days_remaining')
                    ->label(__('app.widgets.days_left'))
                    ->state(fn (Subscription $record): ?int => $record->daysRemaining())
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('start_date')
                    ->label(__('app.fields.start_date'))
                    ->date(),
                TextEntry::make('end_date')
                    ->label(__('app.fields.end_date'))
                    ->date(),
            ]);
    }

    private static function billingSection(): Section
    {
        return Section::make(__('app.titles.summary'))
            ->columns(2)
            ->schema([
                TextEntry::make('invoice.number')
                    ->label(__('app.fields.invoice_number'))
                    ->placeholder(__('app.placeholders.na'))
                    ->url(fn (Subscription $record): ?string => $record->invoice
                        ? InvoiceResource::getUrl('view', ['record' => $record->invoice])
                        : null)
                    ->columnSpanFull(),
                TextEntry::make('invoice.total_amount')
                    ->label(__('app.fields.total'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('invoice.paid_amount')
                    ->label(__('app.fields.paid'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('invoice.due_amount')
                    ->label(__('app.fields.due'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success')
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('invoice.status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('invoice.due_date')
                    ->label(__('app.fields.due_date'))
                    ->date()
                    ->placeholder(__('app.placeholders.na'))
                    ->columnSpanFull(),
            ]);
    }
}
