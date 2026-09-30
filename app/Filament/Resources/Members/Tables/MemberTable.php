<?php

namespace App\Filament\Resources\Members\Tables;

use App\Enums\Status;
use App\Models\Member;
use App\Support\AppConfig;
use App\Support\Filament\StatusAction;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
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
            ->emptyStateHeading(function ($livewire): string {
                $dates = $livewire->getTableFilterState('date') ?? [];
                [$from, $to] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
                $records = (string) __('app.resources.members.plural');
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

                return Member::where('status', $tab)->exists()
                    ? __('app.empty.no_status_records_in_range', ['status' => $status, 'records' => $records])
                    : $base;
            })
            ->emptyStateDescription(function ($livewire): string {
                $dates = $livewire->getTableFilterState('date') ?? [];
                [$fromRaw, $toRaw] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
                $records = (string) __('app.resources.members.plural');
                $record = (string) __('app.resources.members.singular');
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

                if (! Member::where('status', $tab)->exists()) {
                    return __('app.empty.no_records_marked_as', ['records' => $records, 'status' => $status]);
                }

                return __('app.empty.found_none_status_between', ['status' => $status, 'records' => $records, 'from' => $from, 'to' => $to]);
            })
            ->emptyStateActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.members.singular')]))
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
                Filter::make('date')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label(__('app.fields.date_from'))
                            ->native(false)
                            ->suffixIcon('heroicon-o-calendar-days'),
                        DatePicker::make('date_to')
                            ->label(__('app.fields.date_to'))
                            ->native(false)
                            ->suffixIcon('heroicon-o-calendar-days'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
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
                    ])->dropdown(false),
                    ActionGroup::make([
                        Action::make('heading_actions')
                            ->label(__('app.actions.record_actions'))
                            ->disabled()
                            ->color('gray'),
                        ViewAction::make(),
                        EditAction::make()->hiddenLabel(),
                        DeleteAction::make()->hiddenLabel(),
                    ])->dropdown(false),
                ]),
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
