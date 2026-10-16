<?php

namespace App\Filament\Resources\Invoices\RelationManagers;

use App\Helpers\Helpers;
use App\Models\InvoiceTransaction;
use App\Support\AppConfig;
use App\Support\Billing\PaymentMethod;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
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
                    ->dateTime('d M Y, h:i A', AppConfig::timezone()),
                TextColumn::make('payment_method')
                    ->label(__('app.fields.method'))
                    ->formatStateUsing(fn (?string $state): string => $state ? PaymentMethod::channelLabel($state) : __('app.placeholders.dash')),
                TextColumn::make('amount')
                    ->label(__('app.fields.amount'))
                    ->formatStateUsing(fn ($state, InvoiceTransaction $record): string => ($record->type === 'refund' ? '−' : '+').Helpers::formatCurrency($state))
                    ->color(fn (InvoiceTransaction $record): string => $record->type === 'refund' ? 'danger' : 'success')
                    ->weight(FontWeight::SemiBold),
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
            ->columnManager(false)
            ->toolbarActions([]);
    }
}
