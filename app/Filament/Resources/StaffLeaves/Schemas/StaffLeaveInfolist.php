<?php

namespace App\Filament\Resources\StaffLeaves\Schemas;

use App\Enums\Status;
use App\Models\StaffLeave;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffLeaveInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('app.staff_leave.request_details'))
                    ->schema([
                        TextEntry::make('staff.name')
                            ->label(__('app.staff_leave.staff_member')),
                        TextEntry::make('type')
                            ->label(__('app.fields.leave_type')),
                        TextEntry::make('start_date')
                            ->label(__('app.fields.date_from'))
                            ->date(),
                        TextEntry::make('end_date')
                            ->label(__('app.fields.date_to'))
                            ->date(),
                        TextEntry::make('duration')
                            ->label(__('app.fields.duration'))
                            ->state(fn (StaffLeave $record): string => trans_choice(
                                'app.staff_leave.days',
                                $record->durationInDays(),
                                ['count' => $record->durationInDays()],
                            )),
                        TextEntry::make('reason')
                            ->label(__('app.fields.reason')),
                    ])
                    ->columns(3),
                Section::make(__('app.staff_leave.review_details'))
                    ->schema([
                        TextEntry::make('reviewedBy.name')
                            ->label(__('app.fields.reviewed_by'))
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('reviewed_at')
                            ->label(__('app.fields.reviewed_at'))
                            ->dateTime('d M Y, h:i A')
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('review_note')
                            ->label(__('app.fields.review_note'))
                            ->placeholder(__('app.placeholders.na'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (StaffLeave $record): bool => $record->reviewed_at !== null),
                Section::make(__('app.staff_leave.cancellation_details'))
                    ->schema([
                        TextEntry::make('cancelledBy.name')
                            ->label(__('app.fields.cancelled_by'))
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('cancelled_at')
                            ->label(__('app.fields.cancelled_at'))
                            ->dateTime('d M Y, h:i A')
                            ->placeholder(__('app.placeholders.na')),
                        TextEntry::make('cancellation_reason')
                            ->label(__('app.fields.cancellation_reason'))
                            ->placeholder(__('app.placeholders.na'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn (StaffLeave $record): bool => $record->status === Status::Cancelled),
            ]);
    }
}
