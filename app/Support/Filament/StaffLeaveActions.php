<?php

namespace App\Support\Filament;

use App\Enums\Status;
use App\Models\StaffLeave;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;

final class StaffLeaveActions
{
    private function __construct() {}

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label(__('app.actions.approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->authorize(fn (StaffLeave $record): bool => self::canReview($record))
            ->action(function (StaffLeave $record): void {
                /** @var User $reviewer */
                $reviewer = auth()->user();
                $record->approve($reviewer);

                Notification::make()
                    ->title(__('app.notifications.staff_leave_approved'))
                    ->success()
                    ->send();

                self::notifyStaff(
                    $record,
                    __('app.notifications.staff_leave_approved'),
                    __('app.notifications.staff_leave_approved_body', [
                        'reviewer' => $reviewer->name,
                        'period' => self::period($record),
                    ]),
                    Status::Approved,
                );
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label(__('app.actions.reject'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->schema([
                Textarea::make('review_note')
                    ->label(__('app.fields.review_note'))
                    ->rows(3)
                    ->maxLength(1000)
                    ->required(),
            ])
            ->authorize(fn (StaffLeave $record): bool => self::canReview($record))
            ->action(function (StaffLeave $record, array $data): void {
                /** @var User $reviewer */
                $reviewer = auth()->user();
                $record->reject($reviewer, $data['review_note']);

                Notification::make()
                    ->title(__('app.notifications.staff_leave_rejected'))
                    ->success()
                    ->send();

                self::notifyStaff(
                    $record,
                    __('app.notifications.staff_leave_rejected'),
                    __('app.notifications.staff_leave_rejected_body', [
                        'reviewer' => $reviewer->name,
                        'period' => self::period($record),
                    ]),
                    Status::Rejected,
                );
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel_leave')
            ->label(__('app.actions.cancel_leave'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->modalWidth(Width::Small)
            ->schema([
                self::cancellationReasonField(),
            ])
            ->authorize(fn (StaffLeave $record): bool => self::canCancel($record))
            ->action(function (StaffLeave $record, array $data): void {
                /** @var User $actor */
                $actor = auth()->user();
                $wasApproved = $record->status === Status::Approved;

                $record->cancel($actor, $data['cancellation_reason']);

                Notification::make()
                    ->title(__('app.notifications.staff_leave_cancelled'))
                    ->success()
                    ->send();

                if ($wasApproved && $record->staff_id !== $actor->getKey()) {
                    self::notifyStaff(
                        $record,
                        __('app.notifications.staff_leave_cancelled'),
                        __('app.notifications.staff_leave_cancelled_body', [
                            'manager' => $actor->name,
                            'period' => self::period($record),
                        ]),
                        Status::Cancelled,
                    );
                }
            });
    }

    public static function canManage(StaffLeave $record): bool
    {
        return self::canReview($record) || self::canCancel($record);
    }

    private static function canReview(StaffLeave $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $record->canBeReviewedBy($user)
            && $user->can('review', $record);
    }

    private static function canCancel(StaffLeave $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $record->canBeCancelledBy($user)
            && $user->can('cancel', $record);
    }

    private static function cancellationReasonField(): Textarea
    {
        return Textarea::make('cancellation_reason')
            ->label(__('app.fields.cancellation_reason'))
            ->rows(3)
            ->maxLength(1000)
            ->required();
    }

    private static function notifyStaff(
        StaffLeave $record,
        string $title,
        string $body,
        Status $status,
    ): void {
        $notification = Notification::make()
            ->title($title)
            ->body($body);

        $notification = match ($status) {
            Status::Approved => $notification->success(),
            Status::Rejected => $notification->danger(),
            Status::Cancelled => $notification->warning(),
            default => $notification,
        };

        $notification->sendToDatabase($record->staff);
    }

    private static function period(StaffLeave $record): string
    {
        return $record->start_date->format('d M Y').' – '.$record->end_date->format('d M Y');
    }
}
