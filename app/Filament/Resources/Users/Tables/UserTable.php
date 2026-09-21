<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Status;
use App\Models\User;
use App\Support\Filament\StatusAction;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('photo')
                    ->label(__('app.fields.photo'))
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => 'https://ui-avatars.com/api/?background=000&color=fff&name='.$record->name),
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->sortable()
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
                    }),
                TextColumn::make('roles.name')
                    ->label(__('app.fields.role'))
                    ->placeholder(__('app.placeholders.na'))
                    ->searchable()
                    ->formatStateUsing(
                        fn ($state): string => Str::headline($state)
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge(),
            ])
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading(function ($livewire): string {
                $dates = $livewire->getTableFilterState('date') ?? [];
                [$from, $to] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
                $records = __('app.resources.users.plural');
                $tab = (string) ($livewire->activeTab ?? 'all');
                $status = $tab !== 'all' ? (string) __('app.status.'.$tab) : null;

                if (! $from && ! $to) {
                    return $status
                        ? __('app.empty.no_status_records', ['status' => $status, 'records' => $records])
                        : __('app.empty.no_records', ['records' => $records]);
                }

                if ($tab === 'all') {
                    return __('app.empty.no_records_in_range', ['records' => $records]);
                }

                $base = __('app.empty.no_status_records', ['status' => $status, 'records' => $records]);

                return User::where('status', $tab)->exists()
                    ? __('app.empty.no_status_records_in_range', ['status' => $status, 'records' => $records])
                    : $base;
            })
            ->emptyStateDescription(function ($livewire): string {
                $dates = $livewire->getTableFilterState('date') ?? [];
                [$fromRaw, $toRaw] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
                $records = (string) __('app.resources.users.plural');
                $record = (string) __('app.resources.users.singular');
                $tab = (string) ($livewire->activeTab ?? 'all');
                $status = $tab !== 'all' ? (string) __('app.status.'.$tab) : null;

                if (! $fromRaw && ! $toRaw) {
                    return $status
                        ? __('app.empty.no_records_marked_as', ['records' => $records, 'status' => $status])
                        : __('app.empty.create_to_get_started', ['resource' => $record]);
                }

                $from = $fromRaw ? Carbon::parse($fromRaw)->format('d-m-Y') : (string) __('app.common.the_beginning');
                $to = $toRaw ? Carbon::parse($toRaw)->format('d-m-Y') : (string) __('app.common.today');

                if ($tab === 'all') {
                    return __('app.empty.found_none_between', ['records' => $records, 'from' => $from, 'to' => $to]);
                }

                if (! User::where('status', $tab)->exists()) {
                    return __('app.empty.no_records_marked_as', ['records' => $records, 'status' => $status]);
                }

                return __('app.empty.found_none_status_between', ['status' => $status, 'records' => $records, 'from' => $from, 'to' => $to]);
            })
            ->filters([
                TrashedFilter::make(),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label(__('app.fields.date_from')),
                        DatePicker::make('date_to')
                            ->label(__('app.fields.date_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date)
                            );
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        Action::make('heading_actions')
                            ->label(__('app.fields.status'))
                            ->disabled()
                            ->color('gray'),
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
                    ])->dropdown(false),
                    ActionGroup::make([
                        Action::make('heading_actions')
                            ->label(__('app.actions.record_actions'))
                            ->disabled()
                            ->color('gray'),
                        ViewAction::make(),
                        EditAction::make(),
                        DeleteAction::make(),
                        RestoreAction::make(),
                    ])->dropdown(false),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
