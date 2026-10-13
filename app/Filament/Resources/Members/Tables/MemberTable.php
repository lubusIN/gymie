<?php

namespace App\Filament\Resources\Members\Tables;

use App\Enums\Status;
use App\Models\Member;
use App\Support\AppConfig;
use App\Support\Filament\MemberCommunicationActions;
use App\Support\Filament\StatusAction;
use App\Support\Filament\TableUi;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MemberTable
{
    /**
     * Configure the member table schema.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withMax('attendances as last_visit_at', 'checked_in_at')
                ->withCount([
                    'attendances as visits_last_30_days' => fn (Builder $query): Builder => $query
                        ->where('visited_on', '>=', Carbon::now(AppConfig::timezone())->subDays(29)->toDateString()),
                ]))
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('photo')
                    ->circular()
                    ->defaultImageUrl(fn (Member $record): string => 'https://ui-avatars.com/api/?background=000&color=fff&name='.$record->name),
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable()
                    ->label(__('app.fields.name')),
                TextColumn::make('email')
                    ->searchable()
                    ->label(__('app.fields.email')),
                TextColumn::make('gender')
                    ->searchable()
                    ->label(__('app.fields.gender')),
                TextColumn::make('contact')
                    ->searchable()
                    ->label(__('app.fields.contact')),
                TextColumn::make('last_visit_at')
                    ->label(__('app.fields.last_visit'))
                    ->dateTime('d M Y, h:i A')
                    ->placeholder(__('app.attendance.never_visited'))
                    ->sortable(),
                TextColumn::make('visits_last_30_days')
                    ->label(__('app.fields.visits_last_30_days'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('emergency_contact')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('app.fields.emergency_contact')),
                TextColumn::make('created_at')
                    ->sortable()
                    ->date('d-m-Y')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('app.fields.date')),
                TextColumn::make('status')
                    ->badge()
                    ->label(__('app.fields.status')),
            ])
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading(fn ($livewire): string => TableUi::emptyStateHeading($livewire, Member::class, 'members'))
            ->emptyStateDescription(fn ($livewire): string => TableUi::emptyStateDescription($livewire, Member::class, 'members'))
            ->emptyStateActions([
                CreateAction::make()
                    ->hidden(fn (): bool => Member::exists()),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('attendance_recency')
                    ->label(__('app.fields.visit_activity'))
                    ->options([
                        '7' => __('app.attendance.no_visit_for_days', ['days' => 7]),
                        '14' => __('app.attendance.no_visit_for_days', ['days' => 14]),
                        '30' => __('app.attendance.no_visit_for_days', ['days' => 30]),
                        'never' => __('app.attendance.never_visited'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if ($value === 'never') {
                            return $query->whereDoesntHave('attendances');
                        }

                        if (! is_numeric($value)) {
                            return $query;
                        }

                        $cutoff = Carbon::now(AppConfig::timezone())
                            ->subDays((int) $value)
                            ->toDateString();

                        return $query->whereDoesntHave(
                            'attendances',
                            fn (Builder $attendanceQuery): Builder => $attendanceQuery
                                ->where('visited_on', '>=', $cutoff),
                        );
                    }),
                TableUi::dateRangeFilter(),
            ])
            ->recordActions([
                TableUi::actionGroup(
                    primaryLabel: __('app.fields.status'),
                    primaryActions: [
                        StatusAction::make('mark_as_active', Status::Active)
                            ->color('success')
                            ->label(__('app.actions.mark_as_active'))
                            ->requiresConfirmation()
                            ->action(fn (Member $record) => tap($record, function ($record) {
                                $record->update(['status' => 'active']);
                                Notification::make()
                                    ->title(__('app.notifications.member_activated'))
                                    ->success()
                                    ->send();
                            }))
                            ->visible(fn ($record) => $record->status->value === 'inactive'),
                        StatusAction::make('mark_as_inactive', Status::Inactive)
                            ->color('danger')
                            ->label(__('app.actions.mark_as_inactive'))
                            ->requiresConfirmation()
                            ->action(fn (Member $record) => tap($record, function ($record) {
                                $record->update(['status' => 'inactive']);
                                Notification::make()
                                    ->title(__('app.notifications.member_deactivated'))
                                    ->danger()
                                    ->send();
                            }))
                            ->visible(fn ($record) => $record->status->value === 'active'),
                    ],
                    recordActions: [
                        MemberCommunicationActions::sendWelcome()
                            ->hiddenLabel(),
                        ViewAction::make(),
                        EditAction::make()->hiddenLabel(),
                        DeleteAction::make()->hiddenLabel(),
                    ],
                ),
            ])->recordUrl(fn ($record): string => route('filament.admin.resources.members.view', $record->id))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
