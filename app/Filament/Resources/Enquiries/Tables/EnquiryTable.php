<?php

namespace App\Filament\Resources\Enquiries\Tables;

use App\Enums\Status;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Enquiry;
use App\Support\Filament\StatusAction;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
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
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(isToggledHiddenByDefault: true)->label(__('app.fields.id')),
                TextColumn::make('name')->searchable()->sortable()->label(__('app.fields.name')),
                TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: false)->label(__('app.fields.email')),
                TextColumn::make('contact')->toggleable(isToggledHiddenByDefault: true)->label(__('app.fields.contact')),
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
                Filter::make('date')
                    ->schema([
                        DatePicker::make('date_from')->label(__('app.fields.date_from')),
                        DatePicker::make('date_to')->label(__('app.fields.date_to')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ActionGroup::make([
                        Action::make('heading_actions')
                            ->label(__('app.fields.status'))
                            ->disabled()
                            ->visible(fn ($record): bool => in_array($record->status?->value, ['lead'], true))
                            ->color('gray'),
                        Action::make('convert_to_member')
                            ->label(__('app.actions.convert_to_member'))
                            ->icon('heroicon-m-arrows-right-left')
                            ->color('success')
                            ->visible(fn (Enquiry $record): bool => $record->status?->value === 'lead')
                            ->url(fn (Enquiry $record) => MemberResource::getUrl(
                                'create',
                                ['enquiry_id' => $record->id],
                            )),
                        StatusAction::make('mark_as_lost', Status::Lost)
                            ->label(__('app.actions.mark_as_lost'))
                            ->color('danger')
                            ->requiresConfirmation()
                            ->action(fn (Enquiry $record) => tap($record, function ($record) {
                                $record->update(['status' => 'lost']);
                                Notification::make()
                                    ->title(__('app.notifications.enquiry_marked_as_lost'))
                                    ->success()
                                    ->icon('heroicon-o-x-circle')
                                    ->iconColor('danger')
                                    ->send();
                            }))
                            ->visible(fn ($record): bool => $record->status?->value === 'lead'),
                    ])->dropdown(false),
                    ActionGroup::make([
                        Action::make('heading_actions')
                            ->label(__('app.actions.record_actions'))
                            ->disabled()
                            ->color('gray'),
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
