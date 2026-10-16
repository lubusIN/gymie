<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use App\Filament\Infolists\Components\ActivityTimelineEntry;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Filament\Resources\Subscriptions\RelationManagers\InvoicesRelationManager;
use App\Models\Subscription;
use Filament\Infolists\Components\TextEntry;
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
                                self::membershipSection(),
                                self::invoiceTable(),
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
                    ]),
            ]);
    }

    private static function invoiceTable(): LivewireComponent
    {
        return LivewireComponent::make(
            InvoicesRelationManager::class,
            fn (Subscription $record): array => [
                'ownerRecord' => $record,
                'pageClass' => ViewSubscription::class,
            ],
        )
            ->key('invoice_relation_manager')
            ->visible(fn (Subscription $record): bool => InvoicesRelationManager::canViewForRecord(
                $record,
                ViewSubscription::class,
            ));
    }

    private static function membershipSection(): Section
    {
        return Section::make(__('app.ui.subscription_details'))
            ->key('subscription_details')
            ->columns(3)
            ->schema([
                TextEntry::make('member.name')
                    ->label(__('app.fields.member'))
                    ->helperText(fn (Subscription $record): string => $record->member->code)
                    ->url(fn (Subscription $record): string => MemberResource::getUrl('view', [
                        'record' => $record->member,
                    ])),
                TextEntry::make('plan.name')
                    ->label(__('app.fields.plan'))
                    ->helperText(fn (Subscription $record): string => $record->plan->code),
                TextEntry::make('membership_period')
                    ->label(__('app.fields.membership_period'))
                    ->state(fn (Subscription $record): string => self::membershipPeriod($record))
                    ->helperText(fn (Subscription $record): ?string => self::daysRemaining($record)),
            ]);
    }

    private static function membershipPeriod(Subscription $subscription): string
    {
        if ($subscription->start_date === null || $subscription->end_date === null) {
            return __('app.placeholders.na');
        }

        return $subscription->start_date->format('d M Y').' – '.$subscription->end_date->format('d M Y');
    }

    private static function daysRemaining(Subscription $subscription): ?string
    {
        $days = $subscription->daysRemaining();

        return $days === null ? null : $days.' '.__('app.widgets.days_left');
    }
}
