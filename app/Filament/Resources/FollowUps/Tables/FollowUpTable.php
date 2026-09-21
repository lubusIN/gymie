<?php

namespace App\Filament\Resources\FollowUps\Tables;

use App\Enums\Status;
use App\Filament\Resources\FollowUps\FollowUpResource;
use App\Models\Enquiry;
use App\Models\FollowUp;
use App\Models\User;
use App\Support\Data;
use App\Support\Filament\StatusAction;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;

class FollowUpTable
{
    /**
     * Get the follow-up table column definitions.
     *
     * This is used by both the Follow Ups index table and any relation managers
     * that want to reuse the same column set.
     *
     * @return array<int, Column>
     */
    public static function getColumns(): array
    {
        return [
            TextColumn::make('id')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('enquiry.name')
                ->searchable()
                ->label(__('app.resources.enquiries.singular'))
                ->sortable(),
            TextColumn::make('user.name')
                ->searchable()
                ->label(__('app.fields.handled_by'))
                ->placeholder(__('app.placeholders.na'))
                ->sortable(),
            TextColumn::make('method')
                ->label(__('app.fields.method'))
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('schedule_date')
                ->searchable()
                ->date('d-m-Y')
                ->label(__('app.fields.schedule_date'))
                ->toggleable(isToggledHiddenByDefault: false),
            TextColumn::make('status')
                ->badge()
                ->toggleable(isToggledHiddenByDefault: false),
            TextColumn::make('outcome')
                ->toggleable(isToggledHiddenByDefault: true)
                ->placeholder(__('app.placeholders.na'))
                ->limit(40)
                ->tooltip(function (TextColumn $column): ?string {
                    $state = Data::nullableString($column->getState());

                    if ($state === null || strlen($state) <= $column->getCharacterLimit()) {
                        return null;
                    }

                    return $state;
                }),
        ];
    }

    /**
     * Configure the follow-up table schema.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::getColumns())
            ->emptyStateIcon(
                ! Enquiry::exists() ? 'heroicon-o-phone' : 'heroicon-o-arrow-path-rounded-square'
            )
            ->emptyStateHeading(function ($livewire): string {
                // If no enquiry exist
                if (! Enquiry::exists()) {
                    return __('app.empty.no_records', ['records' => __('app.resources.enquiries.plural')]);
                }

                $dates = $livewire->getTableFilterState('date') ?? [];
                [$from, $to] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
                $records = (string) __('app.resources.follow_ups.plural');
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

                return Enquiry::where('status', $tab)->exists()
                    ? __('app.empty.no_status_records_in_range', ['status' => $status, 'records' => $records])
                    : __('app.empty.no_status_records', ['status' => $status, 'records' => $records]);
            })
            ->emptyStateDescription(function ($livewire): string {
                // If no enquiries exist
                if (! Enquiry::exists()) {
                    return __('app.empty.create_to_get_started', ['resource' => __('app.resources.enquiries.singular')]);
                }

                $dates = $livewire->getTableFilterState('date') ?? [];
                [$fromRaw, $toRaw] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
                $records = (string) __('app.resources.follow_ups.plural');
                $record = (string) __('app.resources.follow_ups.singular');
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

                if (! FollowUp::where('status', $tab)->exists()) {
                    return __('app.empty.no_records_marked_as', ['records' => $records, 'status' => $status]);
                }

                return __('app.empty.found_none_status_between', ['status' => $status, 'records' => $records, 'from' => $from, 'to' => $to]);
            })
            ->emptyStateActions([
                Action::make('create_enquiry')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.enquiries.singular')]))
                    ->url(fn () => route('filament.admin.resources.enquiries.create'))
                    ->icon('heroicon-o-plus')
                    ->hidden(fn () => Enquiry::exists()),
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->label(__('app.actions.new_follow_up'))
                    ->createAnother(false)
                    ->modalHeading(__('app.actions.new_follow_up'))
                    ->modalWidth('sm')
                    ->visible(fn () => Enquiry::exists() && ! FollowUp::exists()),
            ])
            ->filters(static::getTableFilters())
            ->recordActions(static::getTableActions())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Get table filter definitions.
     *
     * @return array<int, Filter|TrashedFilter>
     */
    public static function getTableFilters(): array
    {
        return [
            TrashedFilter::make(),
            Filter::make('date')
                ->schema([
                    DatePicker::make('date_from')->label(__('app.fields.date_from')),
                    DatePicker::make('date_to')->label(__('app.fields.date_to')),
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
        ];
    }

    /**
     * Get table action definitions.
     *
     * @return array<int, ActionGroup>
     */
    public static function getTableActions(): array
    {
        return [
            ActionGroup::make([
                ActionGroup::make([
                    Action::make('heading_actions')
                        ->label(__('app.fields.status'))
                        ->visible(fn ($record) => in_array($record->status->value, ['pending']))
                        ->disabled()
                        ->color('gray'),
                    StatusAction::make('mark_as_done', Status::Done)
                        ->color('success')
                        ->label(__('app.actions.mark_as_done'))
                        ->modalWidth('sm')
                        ->fillForm(fn (FollowUp $record): array => [
                            'user_id' => $record->user_id,
                            'outcome' => $record->outcome,
                        ])
                        ->schema([
                            Select::make('user_id')
                                ->label(__('app.fields.handled_by'))
                                ->relationship(name: 'user', titleAttribute: 'name')
                                ->placeholder(__('app.placeholders.select_handler'))
                                ->getOptionLabelFromRecordUsing(function (User $record): string {
                                    $name = html_entity_decode($record->name, ENT_QUOTES, 'UTF-8');
                                    $url = ! empty($record->photo) ? e($record->photo) : "https://ui-avatars.com/api/?background=000&color=fff&name={$name}";

                                    return Blade::render(
                                        '<div class="flex items-center gap-2 h-9">
                                                <x-filament::avatar src="{{ $url }}" alt="{{ $name }}" size="sm" />
                                                <span class="ml-2">{{ $name }}</span>
                                            </div>',
                                        compact('url', 'name')
                                    );
                                })
                                ->allowHtml()
                                ->required(),
                            Textarea::make('outcome')
                                ->placeholder(__('app.placeholders.outcome_example'))
                                ->label(__('app.fields.outcome'))
                                ->required(),
                        ])
                        ->action(function (array $data, FollowUp $record): void {
                            $record->update([
                                'user_id' => $data['user_id'],
                                'outcome' => $data['outcome'],
                                'status' => 'done',
                            ]);
                            Notification::make()
                                ->title(__('app.notifications.follow_up_marked_as_done'))
                                ->success()
                                ->send();
                        })
                        ->visible(fn ($record) => $record->status->value === 'pending'),
                ])->dropdown(false),
                ActionGroup::make([
                    Action::make('heading_actions')
                        ->label(__('app.actions.record_actions'))
                        ->disabled()
                        ->color('gray'),
                    ViewAction::make()
                        ->modalWidth('lg')
                        ->modalCancelAction(false)
                        ->modalAlignment('center')
                        ->schema(
                            fn (Schema $schema): Schema => FollowUpResource::infolist($schema)
                        ),
                    EditAction::make()
                        ->modalWidth('sm')
                        ->hidden(fn ($record): bool => $record->status->value === 'done'),
                    DeleteAction::make(),
                ])->dropdown(false),
            ]),
        ];
    }
}
