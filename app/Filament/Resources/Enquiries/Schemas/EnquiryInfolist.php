<?php

namespace App\Filament\Resources\Enquiries\Schemas;

use App\Enums\Status;
use App\Filament\Infolists\Components\ActivityTimelineEntry;
use App\Filament\Resources\Enquiries\Pages\ViewEnquiry;
use App\Filament\Resources\Enquiries\RelationManagers\FollowUpsRelationManager;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Enquiry;
use App\Support\AppConfig;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire as LivewireComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class EnquiryInfolist
{
    /**
     * Configure the enquiry activity and details layout.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Tabs::make(__('app.resources.enquiries.singular'))
                    ->key('enquiry_sections')
                    ->contained(false)
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make(__('app.ui.details'))
                            ->key('details_tab')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Grid::make([
                                    'default' => 1,
                                    'xl' => 3,
                                ])->schema([
                                    self::leadInformationSection()->columnSpan(2),
                                    self::leadStatusSection(),
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
                        Tab::make(__('app.resources.follow_ups.plural'))
                            ->key('follow_ups_tab')
                            ->icon('heroicon-o-calendar-days')
                            ->visible(fn (Enquiry $record): bool => FollowUpsRelationManager::canViewForRecord(
                                $record,
                                ViewEnquiry::class,
                            ))
                            ->schema([
                                LivewireComponent::make(
                                    FollowUpsRelationManager::class,
                                    fn (Enquiry $record): array => [
                                        'ownerRecord' => $record,
                                        'pageClass' => ViewEnquiry::class,
                                    ],
                                )->key('follow_ups_relation_manager'),
                            ]),
                    ]),
            ]);
    }

    /**
     * Build the primary lead details section.
     */
    private static function leadInformationSection(): Section
    {
        return Section::make(__('app.ui.lead_information'))
            ->columns(3)
            ->schema([
                TextEntry::make('name')
                    ->label(__('app.fields.name')),
                TextEntry::make('email')
                    ->label(__('app.fields.email'))
                    ->copyable()
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('contact')
                    ->label(__('app.fields.contact'))
                    ->copyable()
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('gender')
                    ->label(__('app.fields.gender'))
                    ->formatStateUsing(fn (?string $state): string => self::optionLabel('gender', $state)),
                TextEntry::make('dob')
                    ->label(__('app.fields.dob'))
                    ->date()
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('interested_in')
                    ->label(__('app.fields.interested_in'))
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('source')
                    ->label(__('app.fields.source'))
                    ->formatStateUsing(fn (?string $state): string => self::optionLabel('source', $state)),
                TextEntry::make('goal')
                    ->label(__('app.fields.goal'))
                    ->formatStateUsing(fn (?string $state): string => self::optionLabel('goal', $state)),
                TextEntry::make('country')
                    ->label(__('app.fields.country'))
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('state')
                    ->label(__('app.fields.state'))
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('city')
                    ->label(__('app.fields.city'))
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('pincode')
                    ->label(__('app.fields.pincode'))
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('address')
                    ->label(__('app.fields.address'))
                    ->placeholder(__('app.placeholders.na'))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Build the lead status section.
     */
    private static function leadStatusSection(): Section
    {
        return Section::make(__('app.ui.lead_status'))
            ->columns(1)
            ->schema([
                TextEntry::make('status')
                    ->label(__('app.fields.status'))
                    ->inlineLabel()
                    ->badge(),
                TextEntry::make('date')
                    ->label(__('app.fields.date'))
                    ->inlineLabel()
                    ->date()
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('user.name')
                    ->label(__('app.fields.lead_owner'))
                    ->inlineLabel()
                    ->weight(FontWeight::Bold)
                    ->color('success')
                    ->placeholder(__('app.activity.unassigned'))
                    ->url(fn (Enquiry $record): ?string => $record->user_id
                        ? route('filament.admin.resources.users.view', $record->user_id)
                        : null),
                TextEntry::make('nextFollowUp.schedule_date')
                    ->label(__('app.fields.next_follow_up'))
                    ->inlineLabel()
                    ->date()
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('start_by')
                    ->label(__('app.fields.preferred_start_date'))
                    ->inlineLabel()
                    ->date()
                    ->placeholder(__('app.placeholders.na')),
                TextEntry::make('lost_reason')
                    ->label(__('app.fields.lost_reason'))
                    ->inlineLabel()
                    ->visible(fn (Enquiry $record): bool => $record->status === Status::Lost)
                    ->columnSpanFull(),
                TextEntry::make('converted_at')
                    ->label(__('app.fields.converted_at'))
                    ->inlineLabel()
                    ->dateTime('d M Y, h:i A', AppConfig::timezone())
                    ->visible(fn (Enquiry $record): bool => $record->status === Status::Member),
                TextEntry::make('member.name')
                    ->label(__('app.fields.member'))
                    ->inlineLabel()
                    ->weight(FontWeight::Bold)
                    ->color('success')
                    ->url(fn (Enquiry $record): ?string => $record->member
                        ? MemberResource::getUrl('view', ['record' => $record->member])
                        : null)
                    ->visible(fn (Enquiry $record): bool => $record->member !== null),
            ]);
    }

    /**
     * Translate an option value while preserving custom values.
     */
    private static function optionLabel(string $group, ?string $value): string
    {
        if (blank($value)) {
            return __('app.placeholders.na');
        }

        $key = "app.options.{$group}.{$value}";

        return Lang::has($key) ? __($key) : Str::headline($value);
    }
}
