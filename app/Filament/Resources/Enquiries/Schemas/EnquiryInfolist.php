<?php

namespace App\Filament\Resources\Enquiries\Schemas;

use App\Enums\Status;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Enquiry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class EnquiryInfolist
{
    /**
     * Configure the enquiry infolist schema.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->schema([
                Section::make(__('app.ui.details'))
                    ->heading(function (Enquiry $record): HtmlString {
                        $status = $record->status;

                        if ($status === null) {
                            return new HtmlString(e(__('app.ui.details')));
                        }

                        $html = Blade::render(
                            '<x-filament::badge class="inline-flex" style="margin-left:6px;" :color="$color">
                                        {{ $label }}
                                    </x-filament::badge>',
                            ['color' => $status->getColor(), 'label' => $status->getLabel()]
                        );

                        return new HtmlString(e(__('app.ui.details')).' '.$html);
                    })
                    ->schema([
                        TextEntry::make('name')->label(__('app.fields.name')),
                        TextEntry::make('email')->label(__('app.fields.email'))->copyable(),
                        TextEntry::make('contact')->label(__('app.fields.contact'))->copyable(),
                        TextEntry::make('gender')->label(__('app.fields.gender')),
                        TextEntry::make('dob')
                            ->label(__('app.fields.dob'))
                            ->date(),
                        TextEntry::make('date')
                            ->label(__('app.fields.date'))
                            ->date(),
                        TextEntry::make('user.name')
                            ->label(__('app.fields.lead_owner'))
                            ->weight(FontWeight::Bold)
                            ->color('success')
                            ->url(fn ($record): string => route('filament.admin.resources.users.view', $record->user_id)),
                        TextEntry::make('nextFollowUp.schedule_date')
                            ->label(__('app.fields.next_follow_up'))
                            ->date()
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('start_by')
                            ->label(__('app.fields.preferred_start_date'))
                            ->date()
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('lost_reason')
                            ->label(__('app.fields.lost_reason'))
                            ->visible(fn (Enquiry $record): bool => $record->status === Status::Lost)
                            ->columnSpan(2),
                        TextEntry::make('converted_at')
                            ->label(__('app.fields.converted_at'))
                            ->dateTime()
                            ->visible(fn (Enquiry $record): bool => $record->status === Status::Member),
                        TextEntry::make('member.name')
                            ->label(__('app.fields.member'))
                            ->url(fn (Enquiry $record): ?string => $record->member
                                ? MemberResource::getUrl('view', ['record' => $record->member])
                                : null)
                            ->visible(fn (Enquiry $record): bool => $record->member !== null),
                    ])
                    ->columns(3)
                    ->columnSpan(4),
                Grid::make()
                    ->columnSpan(4)
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'xl' => 5,
                    ])
                    ->schema([
                        Section::make(__('app.ui.location'))
                            ->columnSpan([
                                'default' => 4,
                                'sm' => 1,
                                'xl' => 3,
                            ])
                            ->schema([
                                TextEntry::make('address')->label(__('app.fields.address')),
                                Group::make()
                                    ->schema([
                                        TextEntry::make('country')->label(__('app.fields.country')),
                                        TextEntry::make('state')
                                            ->label(__('app.fields.state'))
                                            ->placeholder(__('app.placeholders.na')),
                                        TextEntry::make('city')
                                            ->label(__('app.fields.city'))
                                            ->placeholder(__('app.placeholders.na')),
                                        TextEntry::make('pincode')
                                            ->label(__('app.fields.pincode')),
                                    ])
                                    ->columns(4),
                            ]),
                        Section::make(__('app.ui.preferences'))
                            ->columnSpan([
                                'default' => 4,
                                'sm' => 1,
                                'xl' => 2,
                            ])
                            ->columns([
                                'sm' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                TextEntry::make('interested_in')
                                    ->label(__('app.fields.interested_in'))
                                    ->columnSpanFull()
                                    ->placeholder(__('app.placeholders.na')),
                                TextEntry::make('source')
                                    ->label(__('app.fields.source')),
                                TextEntry::make('goal')
                                    ->label(__('app.fields.goal')),
                            ]),
                    ]),
            ]);
    }
}
