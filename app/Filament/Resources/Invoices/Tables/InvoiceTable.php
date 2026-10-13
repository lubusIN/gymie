<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Filament\Resources\Subscriptions\RelationManagers\InvoicesRelationManager;
use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\Subscription;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class InvoiceTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->hiddenOn(InvoicesRelationManager::class),
                TextColumn::make('number')
                    ->label(__('app.fields.invoice_number'))
                    ->sortable(),
                TextColumn::make('subscription.member.name')
                    ->label(__('app.fields.subscription'))
                    ->description(fn ($record): string => $record->subscription->member->code)
                    ->hiddenOn(InvoicesRelationManager::class),
                TextColumn::make('date')
                    ->label(__('app.fields.date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label(__('app.fields.due_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('subscription_fee')
                    ->label(__('app.fields.fee'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state)),
                TextColumn::make('discount_amount')
                    ->label(__('app.fields.discount'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state)),
                TextColumn::make('paid_amount')
                    ->label(__('app.fields.paid'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->summarize(
                        Sum::make('paid')
                            ->label(__('app.summaries.paid'))
                            ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state)),
                    ),
                TextColumn::make('tax')
                    ->label(__('app.fields.tax'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state)),
                TextColumn::make('total_amount')
                    ->label(__('app.fields.total'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->summarize(
                        Sum::make('total_invoiced')
                            ->label(__('app.summaries.total_invoiced'))
                            ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state)),
                    ),
                TextColumn::make('due_amount')
                    ->label(__('app.fields.due'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->summarize(
                        Sum::make('outstanding')
                            ->label(__('app.summaries.outstanding'))
                            ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state)),
                    ),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
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
                SelectFilter::make('member')
                    ->label(__('app.fields.member'))
                    ->relationship('subscription.member', 'name')
                    ->searchable(),
                SelectFilter::make('plan')
                    ->label(__('app.fields.plan'))
                    ->relationship('subscription.plan', 'name')
                    ->searchable(),
            ])
            ->emptyStateIcon(
                ! Subscription::exists()
                    ? 'heroicon-o-ticket'
                    : 'heroicon-o-document-text'
            )
            ->emptyStateHeading(function ($livewire): string {
                if (! Subscription::exists()) {
                    return __('app.empty.no_records', ['records' => __('app.resources.subscriptions.plural')]);
                }

                $dates = $livewire->getTableFilterState('date') ?? [];
                [$from, $to] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
                $tab = $livewire->activeTab;
                $records = (string) __('app.resources.invoices.plural');
                $statusKey = $tab === 'partial' ? 'partially_paid' : $tab;
                $status = $tab !== 'all' ? (string) __('app.status.'.$statusKey) : null;
                $heading = $status
                    ? __('app.empty.no_status_records', ['status' => $status, 'records' => $records])
                    : __('app.empty.no_records', ['records' => $records]);

                if (! $from && ! $to) {
                    return $heading;
                }

                if ($tab === 'all') {
                    return __('app.empty.no_records_in_range', ['records' => $records]);
                }

                return Invoice::where('status', $tab)->exists()
                    ? __('app.empty.no_status_records_in_range', ['status' => $status, 'records' => $records])
                    : $heading;
            })
            ->emptyStateDescription(function ($livewire): string {
                if (! Subscription::exists()) {
                    return __('app.empty.create_to_get_started', ['resource' => __('app.resources.subscriptions.singular')]);
                }

                $dates = $livewire->getTableFilterState('date') ?? [];
                [$fromRaw, $toRaw] = [$dates['date_from'] ?? null, $dates['date_to'] ?? null];
                $tab = $livewire->activeTab;
                $records = (string) __('app.resources.invoices.plural');
                $record = (string) __('app.resources.invoices.singular');
                $statusKey = $tab === 'partial' ? 'partially_paid' : $tab;
                $status = $tab !== 'all' ? (string) __('app.status.'.$statusKey) : null;

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

                if (! Invoice::where('status', $tab)->exists()) {
                    return $status
                        ? __('app.empty.no_records_marked_as', ['records' => $records, 'status' => $status])
                        : __('app.empty.create_to_get_started', ['resource' => $record]);
                }

                return __('app.empty.found_none_status_between', ['status' => $status, 'records' => $records, 'from' => $from, 'to' => $to]);
            })
            ->emptyStateActions([
                Action::make('create_subscription')
                    ->label(__('app.actions.new', ['resource' => __('app.resources.subscriptions.singular')]))
                    ->url(fn () => route('filament.admin.resources.subscriptions.create'))
                    ->icon('heroicon-o-plus')
                    ->hidden(fn () => Subscription::exists()),
            ])
            ->recordActions([
                InvoiceTableActions::make(),
            ])
            ->toolbarActions([]);
    }
}
