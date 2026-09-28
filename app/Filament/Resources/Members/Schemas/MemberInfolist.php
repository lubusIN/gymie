<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Filament\Infolists\Components\ActivityTimelineEntry;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\SubscriptionsRelationManager;
use App\Models\Member;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class MemberInfolist
{
    /**
     * Configure the member "view" infolist schema.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make(__('app.resources.members.singular'))
                    ->key('member_sections')
                    ->contained(false)
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('app.ui.details'))
                            ->key('details_tab')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                self::memberInformationSection(),
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
                        Tab::make(__('app.resources.subscriptions.plural'))
                            ->key('subscriptions_tab')
                            ->icon('heroicon-o-ticket')
                            ->visible(fn (Member $record): bool => SubscriptionsRelationManager::canViewForRecord(
                                $record,
                                ViewMember::class,
                            ))
                            ->schema([
                                LivewireComponent::make(
                                    SubscriptionsRelationManager::class,
                                    fn (Member $record): array => [
                                        'ownerRecord' => $record,
                                        'pageClass' => ViewMember::class,
                                    ],
                                )->key('subscriptions_relation_manager'),
                            ]),
                    ]),
            ]);
    }

    private static function memberInformationSection(): Section
    {
        return Section::make()
            ->heading(function (Member $record): HtmlString {
                $status = $record->status;

                if ($status === null) {
                    return new HtmlString(e(__('app.ui.member_information')));
                }

                $html = Blade::render(
                    '<x-filament::badge class="inline-flex ml-2" :color="$color">
                        {{ $label }}
                    </x-filament::badge>',
                    [
                        'color' => $status->getColor(),
                        'label' => $status->getLabel(),
                    ]
                );

                return new HtmlString(e(__('app.ui.member_information')).' '.$html);
            })
            ->schema([
                ImageEntry::make('photo')
                    ->hiddenLabel()
                    ->defaultImageUrl(fn (Member $record): string => 'https://ui-avatars.com/api/?background=000&color=fff&name='.$record->name)
                    ->imageSize(180)
                    ->circular()
                    ->columnSpan(1),
                Group::make()
                    ->schema([
                        TextEntry::make('code')
                            ->label(__('app.fields.member_code')),
                        TextEntry::make('name')->label(__('app.fields.name')),
                        TextEntry::make('gender')->label(__('app.fields.gender')),
                        TextEntry::make('email')->label(__('app.fields.email')),
                        TextEntry::make('contact')->label(__('app.fields.contact')),
                        TextEntry::make('emergency_contact')->label(__('app.fields.emergency_contact'))->placeholder(__('app.placeholders.na')),
                        TextEntry::make('dob')
                            ->label(__('app.fields.dob'))
                            ->date('d-m-Y'),
                        TextEntry::make('source')
                            ->label(__('app.fields.source'))
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('goal')
                            ->label(__('app.fields.goal'))
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('health_issue')
                            ->label(__('app.fields.health_issues'))
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('country')->label(__('app.fields.country')),
                        TextEntry::make('state')
                            ->label(__('app.fields.state'))
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('city')
                            ->label(__('app.fields.city'))
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('pincode')
                            ->label(__('app.fields.pincode')),
                        TextEntry::make('address')->label(__('app.fields.address')),
                    ])->columnSpan(4)->columns(3),
            ])->columns(5);
    }
}
