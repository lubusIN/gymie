<?php

namespace App\Support\Filament;

use App\Enums\CommunicationTemplateKey;
use App\Models\Member;
use App\Services\Communication\CommunicationPreferenceService;
use App\Services\Communication\CommunicationService;
use App\Services\Communication\EmailDeliveryContext;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

final class MemberCommunicationActions
{
    private function __construct() {}

    /** Build the action used to queue a member welcome email. */
    public static function sendWelcome(): Action
    {
        return Action::make('send_welcome_email')
            ->label(__('app.actions.send_welcome_email'))
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->authorize('Update:Member')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-envelope')
            ->modalDescription(__('app.communication.confirm_member_welcome'))
            ->visible(fn (Member $record): bool => filled($record->email))
            ->disabled(fn (Member $record, EmailDeliveryContext $emailContext, CommunicationPreferenceService $preferenceService): bool => ! $emailContext->isDeliveryConfigured()
                || $preferenceService->emailOptOutReason($record) !== null)
            ->tooltip(function (Member $record, EmailDeliveryContext $emailContext, CommunicationPreferenceService $preferenceService): ?string {
                if (! $emailContext->isDeliveryConfigured()) {
                    return $emailContext->unavailableReason();
                }

                return $preferenceService->emailOptOutReason($record);
            })
            ->action(function (Member $record, CommunicationService $communicationService): void {
                $communicationService->queue(
                    templateKey: CommunicationTemplateKey::MemberWelcome,
                    recipient: (string) $record->email,
                    variables: [
                        'member_name' => $record->name,
                        'member_code' => $record->code,
                    ],
                    related: $record,
                    actorId: is_numeric(auth()->id()) ? (int) auth()->id() : null,
                );

                Notification::make()
                    ->title(__('app.notifications.email_queued'))
                    ->body(__('app.notifications.member_welcome_queued_to', ['email' => $record->email]))
                    ->success()
                    ->send();
            });
    }
}
