<?php

namespace App\Filament\Resources\Enquiries\Tables;

use App\Enums\Status;
use App\Models\Enquiry;
use App\Support\Filament\EnquiryPipelineActions;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EnquiryTable
{
    /**
     * Configure the enquiry table schema.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'member',
                'nextFollowUp',
                'user',
            ]))
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(isToggledHiddenByDefault: true)->label(__('app.fields.id')),
                TextColumn::make('name')->searchable()->sortable()->label(__('app.fields.name')),
                TextColumn::make('contact')->searchable()->label(__('app.fields.contact')),
                TextColumn::make('user.name')
                    ->label(__('app.fields.assigned_to'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('source')
                    ->label(__('app.fields.source'))
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (string) __('app.options.source.'.$state)
                        : (string) __('app.placeholders.na')),
                TextColumn::make('nextFollowUp.schedule_date')
                    ->label(__('app.fields.next_follow_up'))
                    ->date('d-m-Y')
                    ->placeholder(__('app.placeholders.na'))
                    ->color(fn (Enquiry $record): ?string => $record->nextFollowUp?->schedule_date?->isBefore(today())
                        ? 'danger'
                        : null),
                TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true)->label(__('app.fields.email')),
                TextColumn::make('date')->sortable()->date('d-m-Y')->toggleable(isToggledHiddenByDefault: true)->label(__('app.fields.date')),
                TextColumn::make('start_by')->date('d-m-Y')->toggleable(isToggledHiddenByDefault: true)->label(__('app.fields.start_by')),
                TextColumn::make('status')
                    ->badge()
                    ->label(__('app.fields.status'))
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateIcon('heroicon-o-phone')
            ->emptyStateHeading(function ($livewire): string {
                $dates = $livewire->getTableFilterState('date') ?? [];
                [$from, $to] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
                $records = (string) __('app.resources.enquiries.plural');
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
                $dates = $livewire->getTableFilterState('date') ?? [];
                [$fromRaw, $toRaw] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
                $records = (string) __('app.resources.enquiries.plural');
                $record = (string) __('app.resources.enquiries.singular');
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

                if (! Enquiry::where('status', $tab)->exists()) {
                    return __('app.empty.no_records_marked_as', ['records' => $records, 'status' => $status]);
                }

                return __('app.empty.found_none_status_between', ['status' => $status, 'records' => $records, 'from' => $from, 'to' => $to]);
            })
            ->emptyStateActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.enquiries.singular')]))
                    ->hidden(fn (): bool => Enquiry::exists()),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('user')
                    ->label(__('app.fields.assigned_to'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('source')
                    ->label(__('app.fields.source'))
                    ->options([
                        'promotions' => __('app.options.source.promotions'),
                        'word_of_mouth' => __('app.options.source.word_of_mouth'),
                        'others' => __('app.options.source.others'),
                    ]),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('date_from')->label(__('app.fields.date_from')),
                        DatePicker::make('date_to')->label(__('app.fields.date_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->where('date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->where('date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        Action::make('heading_actions')
                            ->label(__('app.fields.status'))
                            ->disabled()
                            ->visible(fn (Enquiry $record): bool => ! in_array(
                                $record->status,
                                [Status::Member, Status::Lost],
                                true,
                            ))
                            ->color('gray'),
                        EnquiryPipelineActions::scheduleFollowUp(),
                        EnquiryPipelineActions::convertToMember(),
                        EnquiryPipelineActions::markAsLost(),
                    ])->dropdown(false),
                    ActionGroup::make([
                        Action::make('heading_actions')
                            ->label(__('app.actions.record_actions'))
                            ->disabled()
                            ->color('gray'),
                        EnquiryPipelineActions::viewMember(),
                        EditAction::make()->hiddenLabel(),
                        DeleteAction::make()
                            ->hiddenLabel(),
                    ])->dropdown(false),
                ]),
            ])->recordUrl(fn ($record): string => route('filament.admin.resources.enquiries.view', $record->id))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
