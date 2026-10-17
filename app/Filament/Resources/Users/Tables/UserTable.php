<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Status;
use App\Models\User;
use App\Support\Filament\StatusAction;
use App\Support\Filament\TableUi;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class UserTable
{
    /**
     * Configure the user table schema.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('app.fields.id'))
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('photo')
                    ->label(__('app.fields.photo'))
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => 'https://ui-avatars.com/api/?background=000&color=fff&name='.$record->name),
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('app.fields.email'))
                    ->searchable(),
                TextColumn::make('contact')
                    ->label(__('app.fields.contact'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gender')
                    ->label(__('app.fields.gender'))
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'male' => __('app.options.gender.male'),
                        'female' => __('app.options.gender.female'),
                        'other' => __('app.options.gender.other'),
                        default => filled($state) ? ucfirst((string) $state) : __('app.placeholders.dash'),
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('roles.name')
                    ->label(__('app.fields.role'))
                    ->placeholder(__('app.placeholders.na'))
                    ->searchable()
                    ->formatStateUsing(
                        fn ($state): string => Str::headline($state)
                    ),
                TextColumn::make('joining_date')
                    ->label(__('app.fields.joining_date'))
                    ->date()
                    ->placeholder(__('app.placeholders.na'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge(),
            ])
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading(fn ($livewire): string => TableUi::emptyStateHeading($livewire, User::class, 'users'))
            ->emptyStateDescription(fn ($livewire): string => TableUi::emptyStateDescription($livewire, User::class, 'users'))
            ->filters([
                TableUi::dateRangeFilter(),
            ])
            ->recordActions([
                TableUi::actionGroup(
                    primaryLabel: __('app.fields.status'),
                    primaryActions: [
                        StatusAction::make('inactive', Status::Inactive)
                            ->label(__('app.actions.mark_as_inactive'))
                            ->color('danger')
                            ->requiresConfirmation()
                            ->action(fn (User $record) => tap($record, function ($record) {
                                $record->update(['status' => 'inactive']);
                                Notification::make()
                                    ->title(__('app.notifications.user_inactivated'))
                                    ->danger()
                                    ->body(__('app.notifications.user_inactivated_body', ['name' => $record->name]))
                                    ->send();
                            }))
                            ->visible(fn ($record) => $record->status->value === 'active'),
                        StatusAction::make('active', Status::Active)
                            ->label(__('app.actions.mark_as_active'))
                            ->color('success')
                            ->requiresConfirmation()
                            ->action(fn (User $record) => tap($record, function ($record) {
                                $record->update(['status' => 'active']);
                                Notification::make()
                                    ->title(__('app.notifications.user_activated'))
                                    ->success()
                                    ->body(__('app.notifications.user_activated_body', ['name' => $record->name]))
                                    ->send();
                            }))
                            ->visible(fn ($record) => $record->status->value === 'inactive'),
                    ],
                    recordActions: [
                        ViewAction::make(),
                        EditAction::make(),
                        DeleteAction::make(),
                        RestoreAction::make(),
                    ],
                ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
