<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Helpers\Helpers;
use App\Support\AppConfig;
use App\Support\Billing\PaymentMethod;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvoiceTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.titles.payment_history');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->label(__('app.fields.date'))
                    ->dateTime('d M Y, h:i A', AppConfig::timezone())
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('app.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'payment' => __('app.transactions.payment'),
                        'refund' => __('app.transactions.refund'),
                        default => filled($state) ? ucfirst((string) $state) : __('app.placeholders.dash'),
                    }),
                TextColumn::make('amount')
                    ->label(__('app.fields.amount'))
                    ->formatStateUsing(fn ($state): string => Helpers::formatCurrency($state))
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label(__('app.fields.method'))
                    ->formatStateUsing(fn (?string $state): string => $state ? PaymentMethod::channelLabel($state) : __('app.placeholders.dash')),
                TextColumn::make('note')
                    ->label(__('app.fields.note'))
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'Initial payment' => __('app.transactions.initial_payment'),
                        default => (string) $state,
                    })
                    ->wrap()
                    ->placeholder(__('app.placeholders.dash')),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
