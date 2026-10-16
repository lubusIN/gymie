<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Filament\Resources\Subscriptions\RelationManagers\InvoicesRelationManager;
use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Support\Billing\InvoiceDisplay;
use App\Support\Filament\TableUi;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class InvoiceTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('subscription.member'))
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->hiddenOn(InvoicesRelationManager::class),
                TextColumn::make('number')
                    ->label(__('app.fields.invoice_number'))
                    ->searchable(),
                TextColumn::make('subscription.member.name')
                    ->label(__('app.fields.member'))
                    ->description(fn (Invoice $record): ?string => $record->subscription?->member?->code)
                    ->hiddenOn(InvoicesRelationManager::class),
                TextColumn::make('date')
                    ->label(__('app.fields.invoice_date'))
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label(__('app.fields.due_date'))
                    ->date('d M Y')
                    ->description(fn (Invoice $record): ?string => InvoiceDisplay::overdueAge($record))
                    ->color(fn (Invoice $record): ?string => InvoiceDisplay::overdueAge($record) === null ? null : 'danger')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label(__('app.fields.total'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('due_amount')
                    ->label(__('app.fields.due'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('subscription_fee')
                    ->label(__('app.fields.fee'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('discount_amount')
                    ->label(__('app.fields.discount'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_amount')
                    ->label(__('app.fields.paid'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tax')
                    ->label(__('app.fields.tax'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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

                return TableUi::emptyStateHeading(
                    $livewire,
                    Invoice::class,
                    'invoices',
                    ['partial' => __('app.status.partially_paid')],
                );
            })
            ->emptyStateDescription(function ($livewire): string {
                if (! Subscription::exists()) {
                    return __('app.empty.create_to_get_started', ['resource' => __('app.resources.subscriptions.singular')]);
                }

                return TableUi::emptyStateDescription(
                    $livewire,
                    Invoice::class,
                    'invoices',
                    ['partial' => __('app.status.partially_paid')],
                );
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
