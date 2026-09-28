<?php

namespace App\Support\Filament;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

final class ActivityActions
{
    private function __construct() {}

    public static function addNote(string $logName, string $event, string $placeholder): Action
    {
        return Action::make('add_note')
            ->label(__('app.actions.add_note'))
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('gray')
            ->authorize('update')
            ->modalWidth('sm')
            ->schema([
                Textarea::make('note')
                    ->label(__('app.fields.note'))
                    ->placeholder($placeholder)
                    ->required()
                    ->maxLength(5000)
                    ->rows(5),
            ])
            ->action(function (array $data, Model $record) use ($event, $logName): void {
                activity($logName)
                    ->performedOn($record)
                    ->event($event)
                    ->withProperties(['note' => $data['note']])
                    ->log($event);

                Notification::make()
                    ->title(__('app.notifications.note_added'))
                    ->success()
                    ->send();
            });
    }
}
