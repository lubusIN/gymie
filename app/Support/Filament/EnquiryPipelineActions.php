<?php

namespace App\Support\Filament;

use App\Enums\EnquiryActivityType;
use App\Enums\Status;
use App\Filament\Resources\FollowUps\Schemas\FollowUpForm;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Enquiry;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

final class EnquiryPipelineActions
{
    private function __construct() {}

    public static function addNote(): Action
    {
        return ActivityActions::addNote(
            EnquiryActivityType::LogName,
            EnquiryActivityType::NoteAdded->value,
            __('app.placeholders.activity_note'),
        );
    }

    public static function scheduleFollowUp(): Action
    {
        return Action::make('schedule_follow_up')
            ->label(__('app.actions.schedule_follow_up'))
            ->icon('heroicon-o-calendar-days')
            ->color('warning')
            ->authorize('update')
            ->modalWidth(Width::Small)
            ->schema(FollowUpForm::scheduleFields())
            ->action(function (array $data, Enquiry $record): void {
                $record->followUps()->create([
                    'user_id' => $record->user_id,
                    'method' => $data['method'],
                    'schedule_date' => $data['schedule_date'],
                    'status' => Status::Pending,
                ]);

                Notification::make()
                    ->title(__('app.notifications.follow_up_scheduled'))
                    ->success()
                    ->send();
            })
            ->visible(fn (Enquiry $record): bool => self::isOpen($record)
                && $record->status !== Status::FollowUpScheduled);
    }

    public static function convertToMember(): Action
    {
        return Action::make('convert_to_member')
            ->label(__('app.actions.convert_to_member'))
            ->icon('heroicon-o-arrows-right-left')
            ->color('success')
            ->authorize(fn (Enquiry $record): bool => (auth()->user()?->can('update', $record) ?? false)
                && (auth()->user()?->can('create', Member::class) ?? false))
            ->visible(fn (Enquiry $record): bool => self::isOpen($record) && $record->member === null)
            ->url(fn (Enquiry $record): string => MemberResource::getUrl(
                'create',
                ['enquiry_id' => $record->id],
            ));
    }

    public static function markAsLost(): Action
    {
        return StatusAction::make('mark_as_lost', Status::Lost)
            ->label(__('app.actions.mark_as_lost'))
            ->color('danger')
            ->authorize('update')
            ->requiresConfirmation()
            ->modalWidth(Width::Small)
            ->schema([
                Textarea::make('lost_reason')
                    ->label(__('app.fields.lost_reason'))
                    ->placeholder(__('app.placeholders.lost_reason'))
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(function (array $data, Enquiry $record): void {
                $record->update([
                    'status' => Status::Lost,
                    'lost_reason' => $data['lost_reason'],
                    'converted_at' => null,
                ]);

                Notification::make()
                    ->title(__('app.notifications.enquiry_marked_as_lost'))
                    ->success()
                    ->icon('heroicon-o-x-circle')
                    ->iconColor('danger')
                    ->send();
            })
            ->visible(fn (Enquiry $record): bool => self::isOpen($record));
    }

    public static function viewMember(): Action
    {
        return Action::make('view_member')
            ->label(__('app.actions.view_member'))
            ->icon('heroicon-o-user-circle')
            ->authorize(fn (Enquiry $record): bool => $record->member !== null
                && (auth()->user()?->can('view', $record->member) ?? false))
            ->url(fn (Enquiry $record): string => MemberResource::getUrl(
                'view',
                ['record' => $record->member],
            ))
            ->visible(fn (Enquiry $record): bool => $record->member !== null);
    }

    private static function isOpen(Enquiry $enquiry): bool
    {
        return ! in_array($enquiry->status, [Status::Member, Status::Lost], true);
    }
}
